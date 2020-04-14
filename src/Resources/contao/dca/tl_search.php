<?php

declare(strict_types=1);

/*
 * This file is part of the ContaoNewsCategoriesSearchBundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_search']['fields']['news_categories'] = [
    'relation' => [
        'type' => 'haste-ManyToMany',
        'load' => 'lazy',
        'table' => 'tl_news_category',
        'referenceColumn' => 'search_id',
        'fieldColumn' => 'category_id',
        'relationTable' => 'tl_search_categories',
    ],
];

$GLOBALS['TL_DCA']['tl_search']['fields']['newsId'] = [
    'foreignKey' => 'tl_news.headline',
    'sql' => "int(10) unsigned NOT NULL default '0'",
    'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
];
