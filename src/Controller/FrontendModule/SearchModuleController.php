<?php

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle\Controller\FrontendModule;

use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\ModuleSearch;
use Contao\PageModel;
use Contao\Pagination;
use Contao\Search;
use Contao\SearchResult;
use Contao\StringUtil;
use Contao\System;
use InspiredMinds\ContaoNewsCategoriesSearchBundle\Event\SearchResultEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @FrontendModule("search", category="application")
 */
class SearchModuleController extends ModuleSearch
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __invoke(ModuleModel $model, string $section): Response
    {
        parent::__construct($model, $section);

        return new Response($this->generate());
    }

    /**
     * Copy of ModuleSearch::compile - plus event.
     */
    protected function compile(): void
    {
        // Mark the x and y parameter as used (see #4277)
        if (isset($_GET['x']))
        {
            Input::get('x');
            Input::get('y');
        }

        // Trigger the search module from a custom form
        if (!isset($_GET['keywords']) && Input::post('FORM_SUBMIT') == 'tl_search')
        {
            $_GET['keywords'] = Input::post('keywords');
            $_GET['query_type'] = Input::post('query_type');
            $_GET['per_page'] = Input::post('per_page');
        }

        $blnFuzzy = (bool) $this->fuzzy;
        $strQueryType = Input::get('query_type') ?: $this->queryType;

        if (\is_array(Input::get('keywords')))
        {
            throw new BadRequestHttpException('Expected string, got array');
        }

        $strKeywords = trim(Input::get('keywords'));

        $this->Template->uniqueId = $this->id;
        $this->Template->queryType = $strQueryType;
        $this->Template->keyword = StringUtil::specialchars($strKeywords);
        $this->Template->keywordLabel = $GLOBALS['TL_LANG']['MSC']['keywords'];
        $this->Template->optionsLabel = $GLOBALS['TL_LANG']['MSC']['options'];
        $this->Template->search = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['searchLabel']);
        $this->Template->matchAll = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['matchAll']);
        $this->Template->matchAny = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['matchAny']);
        $this->Template->advanced = ($this->searchType == 'advanced');

        // Redirect page
        if (($objTarget = $this->objModel->getRelated('jumpTo')) instanceof PageModel)
        {
            /** @var PageModel $objTarget */
            $this->Template->action = $objTarget->getFrontendUrl();
        }

        $this->Template->pagination = '';
        $this->Template->results = '';

        // Execute the search if there are keywords
        if ($strKeywords !== '' && $strKeywords != '*' && !$this->jumpTo)
        {
            // Search pages
            if (!empty($this->pages) && \is_array($this->pages))
            {
                $arrPages = array();

                foreach ($this->pages as $intPageId)
                {
                    $arrPages[] = array($intPageId);
                    $arrPages[] = $this->Database->getChildRecords($intPageId, 'tl_page');
                }

                if (!empty($arrPages))
                {
                    $arrPages = array_merge(...$arrPages);
                }

                $arrPages = array_unique($arrPages);
            }
            // Website root
            else
            {
                /** @var PageModel $objPage */
                global $objPage;

                $arrPages = $this->Database->getChildRecords($objPage->rootId, 'tl_page');
            }

            // HOOK: add custom logic (see #5223)
            if (isset($GLOBALS['TL_HOOKS']['customizeSearch']) && \is_array($GLOBALS['TL_HOOKS']['customizeSearch']))
            {
                foreach ($GLOBALS['TL_HOOKS']['customizeSearch'] as $callback)
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($arrPages, $strKeywords, $strQueryType, $blnFuzzy, $this);
                }
            }

            // Return if there are no pages
            if (empty($arrPages) || !\is_array($arrPages))
            {
                return;
            }

            $query_starttime = microtime(true);

            try
            {
                $objResult = Search::query($strKeywords, ($strQueryType == 'or'), $arrPages, $blnFuzzy, (int) $this->minKeywordLength);
            }
            catch (\Exception $e)
            {
                System::getContainer()->get('monolog.logger.contao.error')->error('Website search failed: ' . $e->getMessage());

                $objResult = new SearchResult(array());
            }

            $query_endtime = microtime(true);

            // Sort out protected pages
            if (Config::get('indexProtected'))
            {
                $objResult->applyFilter(static function ($v)
                {
                    return empty($v['protected']) || System::getContainer()->get('security.helper')->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($v['groups'] ?? null, true));
                });
            }

            // Dispatch event for additional filtering
            $this->eventDispatcher->dispatch(new SearchResultEvent(
                $objResult,
                $this->objModel,
                $arrPages,
                $strKeywords,
                $strQueryType,
            ));

            $count = $objResult->getCount();

            $this->Template->count = $count;
            $this->Template->page = null;
            $this->Template->keywords = $strKeywords;

            if ($this->minKeywordLength > 0)
            {
                $this->Template->keywordHint = sprintf($GLOBALS['TL_LANG']['MSC']['sKeywordHint'], $this->minKeywordLength);
            }

            // No results
            if ($count < 1)
            {
                $this->Template->header = sprintf($GLOBALS['TL_LANG']['MSC']['sEmpty'], $strKeywords);
                $this->Template->duration = System::getFormattedNumber($query_endtime - $query_starttime, 3) . ' ' . $GLOBALS['TL_LANG']['MSC']['seconds'];

                return;
            }

            $from = 1;
            $to = $count;

            // Pagination
            if ($this->perPage > 0)
            {
                $id = 'page_s' . $this->id;
                $page = (int) (Input::get($id) ?? 1);
                $per_page = (int) Input::get('per_page') ?: $this->perPage;

                // Do not index or cache the page if the page number is outside the range
                if ($page < 1 || $page > max(ceil($count/$per_page), 1))
                {
                    throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
                }

                $from = (($page - 1) * $per_page) + 1;
                $to = (($from + $per_page) > $count) ? $count : ($from + $per_page - 1);

                // Pagination menu
                if ($to < $count || $from > 1)
                {
                    $objPagination = new Pagination($count, $per_page, Config::get('maxPaginationLinks'), $id);
                    $this->Template->pagination = $objPagination->generate("\n  ");
                }

                $this->Template->page = $page;
            }

            $contextLength = 48;
            $totalLength = 360;

            $lengths = StringUtil::deserialize($this->contextLength, true) + array(null, null);

            if ($lengths[0] > 0)
            {
                $contextLength = $lengths[0];
            }

            if ($lengths[1] > 0)
            {
                $totalLength = $lengths[1];
            }

            $arrResult = $objResult->getResults($to-$from+1, $from-1);

            // Get the results
            foreach (array_keys($arrResult) as $i)
            {
                $objTemplate = new FrontendTemplate($this->searchTpl ?: 'search_default');
                $objTemplate->setData($arrResult[$i]);
                $objTemplate->href = $arrResult[$i]['url'];
                $objTemplate->link = $arrResult[$i]['title'];
                $objTemplate->url = StringUtil::specialchars(urldecode($arrResult[$i]['url']), true, true);
                $objTemplate->title = StringUtil::specialchars(StringUtil::stripInsertTags($arrResult[$i]['title']));
                $objTemplate->class = ($i == 0 ? 'first ' : '') . ((empty($arrResult[$i+1])) ? 'last ' : '') . (($i % 2 == 0) ? 'even' : 'odd');
                $objTemplate->relevance = sprintf($GLOBALS['TL_LANG']['MSC']['relevance'], number_format($arrResult[$i]['relevance'] / $arrResult[0]['relevance'] * 100, 2) . '%');
                $objTemplate->unit = $GLOBALS['TL_LANG']['UNITS'][1];

                $arrContext = array();
                $strText = StringUtil::stripInsertTags(strtok($arrResult[$i]['text'], "\n"));
                $arrMatches = Search::getMatchVariants(StringUtil::trimsplit(',', $arrResult[$i]['matches']), $strText, $GLOBALS['TL_LANGUAGE']);

                // Get the context
                foreach ($arrMatches as $strWord)
                {
                    $arrChunks = array();
                    preg_match_all('/(^|(?:\b|^).{0,' . $contextLength . '}(?:\PL|\p{Hiragana}|\p{Katakana}|\p{Han}|\p{Myanmar}|\p{Khmer}|\p{Lao}|\p{Thai}|\p{Tibetan}))' . preg_quote($strWord, '/') . '((?:\PL|\p{Hiragana}|\p{Katakana}|\p{Han}|\p{Myanmar}|\p{Khmer}|\p{Lao}|\p{Thai}|\p{Tibetan}).{0,' . $contextLength . '}(?:\b|$)|$)/ui', $strText, $arrChunks);

                    foreach ($arrChunks[0] as $strContext)
                    {
                        $arrContext[] = ' ' . $strContext . ' ';
                    }

                    // Skip other terms if the total length is already reached
                    if (array_sum(array_map('mb_strlen', $arrContext)) >= $totalLength)
                    {
                        break;
                    }
                }

                // Shorten the context and highlight all keywords
                if (!empty($arrContext))
                {
                    $objTemplate->context = trim(StringUtil::substrHtml(implode('…', $arrContext), $totalLength));
                    $objTemplate->context = preg_replace('((?<=^|\PL|\p{Hiragana}|\p{Katakana}|\p{Han}|\p{Myanmar}|\p{Khmer}|\p{Lao}|\p{Thai}|\p{Tibetan})(' . implode('|', array_map('preg_quote', $arrMatches)) . ')(?=\PL|\p{Hiragana}|\p{Katakana}|\p{Han}|\p{Myanmar}|\p{Khmer}|\p{Lao}|\p{Thai}|\p{Tibetan}|$))ui', '<mark class="highlight">$1</mark>', $objTemplate->context);

                    $objTemplate->hasContext = true;
                }

                $this->addImageToTemplateFromSearchResult($arrResult[$i], $objTemplate);

                $this->Template->results .= $objTemplate->parse();
            }

            $this->Template->header = vsprintf($GLOBALS['TL_LANG']['MSC']['sResults'], array($from, $to, $count, $strKeywords));
            $this->Template->duration = System::getFormattedNumber($query_endtime - $query_starttime, 3) . ' ' . $GLOBALS['TL_LANG']['MSC']['seconds'];
        }
    }
}
