<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener\ParseTemplate;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\NewsModel;
use Contao\Template;
use Doctrine\DBAL\Connection;

/**
 * Sets the news record for the search record in the search_* template.
 */
#[AsHook('parseTemplate')]
class SearchListener
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function __invoke(Template $template): void
    {
        if (0 !== stripos($template->getName(), 'search_') || empty($template->url)) {
            return;
        }

        $searchEntry = $this->db->fetchAssociative('SELECT * FROM `tl_search` WHERE `url` = ? LIMIT 1', [$template->url]);

        if (false === $searchEntry || empty($searchEntry['newsId'])) {
            return;
        }

        $news = NewsModel::findById($searchEntry['newsId']);

        if (null === $news) {
            return;
        }

        $template->news = $news;
    }
}
