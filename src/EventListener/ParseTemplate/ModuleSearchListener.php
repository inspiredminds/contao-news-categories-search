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

use Codefog\NewsCategoriesBundle\Model\NewsCategoryModel;
use Contao\Input;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Template;

/**
 * Sets the filter options in the mod_search template.
 */
class ModuleSearchListener
{
    public const OPTION_NAME = 'categories';

    public function __invoke(Template $template): void
    {
        if (0 !== stripos($template->getName(), 'mod_search')) {
            return;
        }

        $template->categoryFilterLegend = $GLOBALS['TL_LANG']['MSC']['search_filterByCategories'];
        $template->timeframeFilterLegend = $GLOBALS['TL_LANG']['MSC']['search_filterByTime'];
        $template->startDateLabel = $GLOBALS['TL_LANG']['MSC']['search_filterStartDate'];
        $template->endDateLabel = $GLOBALS['TL_LANG']['MSC']['search_filterEndDate'];

        $module = ModuleModel::findById($template->id);

        if (null === $module) {
            return;
        }

        if ($module->search_enableNewsCategoriesFilter) {
            $categories = null;

            if ($categoryIds = StringUtil::deserialize($module->news_categories)) {
                $categories = NewsCategoryModel::findMultipleByIds(array_map('intval', $categoryIds));
            } else {
                $categories = NewsCategoryModel::findAll();
            }

            if (null !== $categories) {
                $categoryOptions = [];
                $selected = array_map('intval', Input::get(self::OPTION_NAME) ?? []);

                /** @var NewsCategoryModel $category */
                foreach ($categories as $category) {
                    $categoryOptions[] = [
                        'name' => self::OPTION_NAME.'[]',
                        'id' => $category->id,
                        'value' => $category->id,
                        'label' => $category->frontendTitel ?: $category->title,
                        'checked' => \in_array((int) $category->id, $selected, true) ? ' checked' : '',
                    ];
                }

                $template->newsCategoriesOptions = $categoryOptions;
            }
        }

        if ($module->search_enableTimeFilter) {
            $template->startDate = Input::get('start');
            $template->endDate = Input::get('end');
        }
    }
}
