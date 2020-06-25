<?php

declare(strict_types=1);

/*
 * This file is part of the ContaoNewsCategoriesSearchBundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener\ParseTemplate;

use Contao\NewsModel;
use Contao\Template;
use Doctrine\DBAL\Connection;

/**
 * Sets the news record for the search record in the search_* template.
 */
class SearchListener
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function __invoke(Template $template): void
    {
        if (0 !== stripos($template->getName(), 'search_') || empty($template->url)) {
            return;
        }

        $searchEntry = $this->db->fetchAssoc('SELECT * FROM `tl_search` WHERE `url` = ? LIMIT 1', [$template->url]);

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
