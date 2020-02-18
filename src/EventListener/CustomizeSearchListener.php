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
use Contao\Search;
use Contao\StringUtil;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Implements a "customizeSearch" Hook.
 *
 * The Hook will execute the search in advance, and filter the results according
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
        // No actions needed, if not enabled
        if (!$module->search_enableNewsCategoriesFilter) {
            return;
        }

        // No actions needed, if no pages to be searched are given
        if (empty($pageIds) || !\is_array($pageIds)) {
            return;
        }

        $categoryIds = Input::get(ParseTemplateListener::OPTION_NAME);

        // No actions needed, if no catgories are selected
        if (empty($categoryIds)) {
            return;
        }

        $categoryIds = array_map('intval', $categoryIds);
        $allowedCategoryIds = array_map('intval', StringUtil::deserialize($module->news_categories, true));

        if (!empty($allowedCategoryIds)) {
            $categoryIds = array_intersect($categoryIds, $allowedCategoryIds);
        }

        // Modify "queryType" for caching
        $originalQueryType = $queryType;
        $queryType .= '|'.ParseTemplateListener::OPTION_NAME.':'.implode(',', $categoryIds).'|';

        // Determine cache file
        global $objPage;
        $rootId = $this->rootPage ?: $objPage->rootId;
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

        $results = [];

        try {
            $objSearch = Search::searchFor($keywords, ('or' === $originalQueryType), $pageIds, 0, 0, $fuzzy);
            $results = $objSearch->fetchAllAssoc();
        } catch (\Exception $e) {
            return;
        }

        // Filter the results according to the selected categories
        $categoryReferences = array_map('intval', \Haste\Model\Model::getReferenceValues('tl_search', 'news_categories', $categoryIds));
        $filteredResults = [];

        foreach ($results as $result) {
            if (\in_array((int) $result['id'], $categoryReferences, true)) {
                $filteredResults[] = $result;
            }
        }

        // Write a cache file
        $fs->dumpFile($cacheFile, json_encode($filteredResults));
    }
}
