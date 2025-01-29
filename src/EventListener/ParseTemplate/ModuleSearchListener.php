<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener\ParseTemplate;

use Codefog\NewsCategoriesBundle\Model\NewsCategoryModel;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Input;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Template;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sets the filter options in the mod_search template.
 *
 * @Hook("parseTemplate")
 */
class ModuleSearchListener
{
    public const OPTION_NAME = 'categories';

    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function __invoke(Template $template): void
    {
        if (0 !== stripos($template->getName(), 'mod_search')) {
            return;
        }

        $template->categoryFilterLegend = $this->translator->trans('MSC.search_filterByCategories', [], 'contao_default');
        $template->timeframeFilterLegend = $this->translator->trans('MSC.search_filterByTime', [], 'contao_default');
        $template->startDateLabel = $this->translator->trans('MSC.search_filterStartDate', [], 'contao_default');
        $template->endDateLabel = $this->translator->trans('MSC.search_filterEndDate', [], 'contao_default');

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
