<?php

declare(strict_types=1);

/*
 * This file is part of the ContaoNewsCategoriesSearchBundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_module']['fields']['news_categories']['eval']['tl_class'] = 'clr';

$GLOBALS['TL_DCA']['tl_module']['fields']['search_enableNewsCategoriesFilter'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['search_enableNewsCategoriesFilter'],
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql' => ['type' => 'boolean', 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'search_enableNewsCategoriesFilter';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['search_enableNewsCategoriesFilter'] = 'news_categories';

PaletteManipulator::create()
    ->addLegend('newscategories_legend', 'config_legend')
    ->addField('search_enableNewsCategoriesFilter', 'newscategories_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('search', 'tl_module')
;
