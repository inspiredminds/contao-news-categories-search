<?php

declare(strict_types=1);

/*
 * This file is part of the ContaoNewsCategoriesSearchBundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener;

use Contao\File;
use Contao\Input;
use Contao\Module;
use Contao\NewsModel;
use Contao\Search;
use Contao\StringUtil;
use InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener\ParseTemplate\ModuleSearchListener;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Implements a "customizeSearch" Hook.
 *
 * The Hook will execute the search in advance, and filter the results according to
 * the given categories. It will then modify the query type and create a cache file
 * directly with the modified query type. This will cause \Contao\ModuleSearch to
 * load the existing cache file of the search with the already filtered results.
 */
class CustomizeSearchListener
{
    private $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    public function __invoke(array &$pageIds, string $keywords, string &$queryType, bool $fuzzy, Module $module): void
    {
        // No actions needed, if no pages to be searched are given
        if (empty($pageIds) || !\is_array($pageIds)) {
            return;
        }

        // Save the original "queryType"
        $originalQueryType = $queryType;

        // Filter by categories
        $categoryIds = [];

        if ($module->search_enableNewsCategoriesFilter) {
            $categoryIds = Input::get(ModuleSearchListener::OPTION_NAME);

            if (!empty($categoryIds)) {
                $categoryIds = array_map('intval', $categoryIds);
                $allowedCategoryIds = array_map('intval', StringUtil::deserialize($module->news_categories, true));

                if (!empty($allowedCategoryIds)) {
                    $categoryIds = array_intersect($categoryIds, $allowedCategoryIds);
                }

                // Modify "queryType" for caching
                $queryType .= '|'.ModuleSearchListener::OPTION_NAME.':'.implode(',', $categoryIds).'|';
            }
        }

        if ($module->search_news_sorting) {
            $queryType .= '|'.$module->search_news_sorting.'|';
        }

        // Filter by timeframe
        $startDate = null;
        $endDate = null;

        if ($module->search_enableTimeFilter) {
            $start = Input::get('start');
            $end = Input::get('end');

            if (!empty($start) && !empty($end)) {
                $start = strtotime($start);
                $end = strtotime($end);

                if ($start > 0 && $end > 0) {
                    $startDate = $start;
                    $endDate = $end;

                    // Modify "queryType" for caching
                    $queryType .= '|'.$startDate.'-'.$endDate.'|';
                }
            }
        }

        // Early out
        if ((empty($startDate) || empty($endDate)) && empty($categoryIds) && empty($module->search_news_sorting)) {
            $queryType = $originalQueryType;

            return;
        }

        // Determine cache file
        global $objPage;
        $rootId = $module->rootPage ?: $objPage->rootId;
        $cacheChecksum = md5($keywords.$queryType.$rootId.$fuzzy);
        $cacheFile = $this->cacheDir.'/contao/search/'.$cacheChecksum.'.json';

        $fs = new Filesystem();

        if ($fs->exists($cacheFile)) {
            $file = new File(StringUtil::stripRootDir($cacheFile));

            if ($file->mtime > time() - 1800) {
                return;
            }

            $file->delete();
        }

        // Fetch the search result
        $results = [];

        try {
            $objSearch = Search::searchFor($keywords, ('or' === $originalQueryType), $pageIds, 0, 0, $fuzzy);
            $results = $objSearch->fetchAllAssoc();
        } catch (\Exception $e) {
            return;
        }

        // Filter the results according to the selected categories
        if ($module->search_enableNewsCategoriesFilter && !empty($categoryIds)) {
            $categoryReferences = array_map('intval', \Haste\Model\Model::getReferenceValues('tl_search', 'news_categories', $categoryIds));
            $filteredResults = [];

            foreach ($results as $result) {
                if (\in_array((int) $result['id'], $categoryReferences, true)) {
                    $filteredResults[] = $result;
                }
            }

            $results = $filteredResults;
        }

        // Filter the results according to start and end date
        if ($module->search_enableTimeFilter && !empty($startDate) && !empty($endDate)) {
            $filteredResults = [];

            foreach ($results as $result) {
                if (!empty($result['newsId']) && null !== ($news = NewsModel::findById($result['newsId']))) {
                    if ((int) $news->date >= $startDate && (int) $news->date <= $endDate) {
                        $filteredResults[] = $result;
                    }
                }
            }

            $results = $filteredResults;
        }

        // Sort the results
        if (!empty($module->search_news_sorting)) {
            usort($results, function ($a, $b) use ($module) {
                $na = NewsModel::findById($a['newsId']);
                $nb = NewsModel::findById($b['newsId']);

                if (null !== $na && null !== $nb) {
                    if ('date_asc' === $module->search_news_sorting) {
                        $temp = $na;
                        $na = $nb;
                        $nb = $temp;
                    }

                    if ((int) $na->date !== (int) $nb->date) {
                        return (int) $nb->date - (int) $na->date;
                    }
                }

                return (float) $b['relevance'] - (float) $a['relevance'];
            });

            $maxRelevance = 0;
            $newsItemsCount = 0;

            foreach ($results as $result) {
                if ((float) $result['relevance'] > $maxRelevance) {
                    $maxRelevance = (float) $result['relevance'];
                }

                if (!empty($result['newsId'])) {
                    ++$newsItemsCount;
                }
            }

            $maxRelevance += $newsItemsCount;

            foreach ($results as &$result) {
                if (!empty($result['newsId'])) {
                    $result['relevance'] = $maxRelevance;
                    --$maxRelevance;
                }
            }
        }

        // Write a cache file
        $fs->dumpFile($cacheFile, json_encode($results));
    }
}
