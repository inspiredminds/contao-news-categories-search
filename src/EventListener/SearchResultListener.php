<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener;

use Codefog\HasteBundle\Model\DcaRelationsModel;
use Contao\NewsModel;
use Contao\SearchResult;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Haste\Model\Model as HasteModel;
use InspiredMinds\ContaoNewsCategoriesSearchBundle\Event\SearchResultEvent;
use InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener\ParseTemplate\ModuleSearchListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SearchResultListener
{
    private RequestStack $requestStack;

    private Connection $db;

    public function __construct(RequestStack $requestStack, Connection $db)
    {
        $this->requestStack = $requestStack;
        $this->db = $db;
    }

    public function __invoke(SearchResultEvent $event): void
    {
        if (!$event->getPageIds()) {
            return;
        }

        if (!$request = $this->requestStack->getCurrentRequest()) {
            return;
        }

        $module = $event->getSearchModuleModel();

        if ($module->search_enableNewsCategoriesFilter) {
            $this->filterByCategories($event, $request);
        }

        if ($module->search_enableTimeFilter) {
            $this->filterByTime($event, $request);
        }

        if ($module->search_news_sorting) {
            $this->sortByNewsDate($event);
        }
    }

    private function filterByCategories(SearchResultEvent $event, Request $request): void
    {
        if (!$categoryIds = $request->query->all(ModuleSearchListener::OPTION_NAME)) {
            return;
        }

        $categoryIds = array_map('intval', $categoryIds);
        $allowedCategoryIds = array_map('intval', StringUtil::deserialize($event->getSearchModuleModel()->news_categories, true));

        if ($allowedCategoryIds) {
            $categoryIds = array_intersect($categoryIds, $allowedCategoryIds);
        }

        if (!$categoryIds) {
            return;
        }

        if (class_exists(HasteModel::class)) {
            $newsIds = HasteModel::getReferenceValues('tl_search', 'news_categories', $categoryIds);
        } else {
            $newsIds = DcaRelationsModel::getReferenceValues('tl_search', 'news_categories', $categoryIds);
        }

        $newsIds = array_map('intval', $newsIds);

        $event->getSearchResult()->applyFilter(static fn (array $result): bool => \in_array((int) $result['id'], $newsIds, true));
    }

    private function filterByTime(SearchResultEvent $event, Request $request): void
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');

        if (!$start || !$end) {
            return;
        }

        $start = strtotime($start);
        $end = strtotime($end);

        if (!$start || !$end) {
            return;
        }

        $event->getSearchResult()->applyFilter(
            static function (array $result) use ($start, $end): bool {
                if (!$result['newsId'] ?? null) {
                    return false;
                }

                if (!$news = NewsModel::findById($result['newsId'])) {
                    return false;
                }

                return (int) $news->date >= $start && (int) $news->date <= $end;
            },
        );
    }

    private function sortByNewsDate(SearchResultEvent $event): void
    {
        $result = $event->getSearchResult();
        $module = $event->getSearchModuleModel();

        $searchResultProperty = (new \ReflectionClass(SearchResult::class))->getProperty('arrResultsById');
        $searchResultProperty->setAccessible(true);

        $results = $searchResultProperty->getValue($result);

        $newsIds = $this->db->fetchAllKeyValue('SELECT id, newsId FROM tl_search WHERE id IN (?)', [array_keys($results)], [ArrayParameterType::INTEGER]);

        uasort(
            $results,
            static function ($a, $b) use ($module, $newsIds) {
                $na = NewsModel::findById($newsIds[$a['id']] ?? 0);
                $nb = NewsModel::findById($newsIds[$b['id']] ?? 0);

                if ($na && $nb) {
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
            },
        );

        $searchResultProperty->setValue($result, $results);
    }
}
