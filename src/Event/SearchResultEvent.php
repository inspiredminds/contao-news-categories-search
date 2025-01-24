<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle\Event;

use Contao\ModuleModel;
use Contao\SearchResult;
use Symfony\Contracts\EventDispatcher\Event;

class SearchResultEvent extends Event
{
    /**
     * @param list<int> $pageIds
     */
    public function __construct(
        private readonly SearchResult $searchResult,
        private readonly ModuleModel $searchModuleModel,
        private readonly array $pageIds,
        private readonly string $keywords,
        private readonly string $queryType,
    ) {
    }

    public function getSearchResult(): SearchResult
    {
        return $this->searchResult;
    }

    public function getSearchModuleModel(): ModuleModel
    {
        return $this->searchModuleModel;
    }

    public function getPageIds(): array
    {
        return $this->pageIds;
    }

    public function getKeywords(): string
    {
        return $this->keywords;
    }

    public function getQueryType(): string
    {
        return $this->queryType;
    }
}
