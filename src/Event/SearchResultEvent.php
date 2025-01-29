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
    private SearchResult $searchResult;

    private ModuleModel $searchModuleModel;

    private array $pageIds;

    private string $keywords;

    private string $queryType;

    /**
     * @param list<int> $pageIds
     */
    public function __construct(SearchResult $searchResult, ModuleModel $searchModuleModel, array $pageIds, string $keywords, string $queryType)
    {
        $this->searchResult = $searchResult;
        $this->searchModuleModel = $searchModuleModel;
        $this->pageIds = $pageIds;
        $this->keywords = $keywords;
        $this->queryType = $queryType;
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
