<?php

declare(strict_types=1);

/*
 * This file is part of the ContaoNewsCategoriesSearchBundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

use InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener\CustomizeSearchListener;
use InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener\IndexNewsCategoriesListener;
use InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener\ParseTemplate\ModuleSearchListener;
use InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener\ParseTemplate\SearchListener;

$GLOBALS['TL_HOOKS']['parseTemplate'][] = [ModuleSearchListener::class, '__invoke'];
$GLOBALS['TL_HOOKS']['parseTemplate'][] = [SearchListener::class, '__invoke'];
$GLOBALS['TL_HOOKS']['parseArticles'][] = [IndexNewsCategoriesListener::class, 'onParseArticles'];
$GLOBALS['TL_HOOKS']['customizeSearch'][] = [CustomizeSearchListener::class, '__invoke'];
