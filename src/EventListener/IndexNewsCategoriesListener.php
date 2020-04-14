<?php

declare(strict_types=1);

/*
 * This file is part of the ContaoNewsCategoriesSearchBundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener;

use Codefog\NewsCategoriesBundle\Model\NewsCategoryModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Module;
use Contao\ModuleNewsReader;
use Contao\NewsModel;
use Doctrine\DBAL\Connection;
use Haste\Model\Relations;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Contracts\Service\ResetInterface;

class IndexNewsCategoriesListener implements ResetInterface
{
    /**
     * @var bool
     */
    private $queueIndexing = false;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Connection
     */
    private $db;

    public function __construct(ContaoFramework $framework, Connection $db)
    {
        $this->framework = $framework;
        $this->db = $db;
    }

    public function onParseArticles(FrontendTemplate $template, array $newsEntry, Module $module): void
    {
        if ($module instanceof ModuleNewsReader) {
            $this->queueIndexing = true;
        }
    }

    public function onKernelTerminate(PostResponseEvent $event): void
    {
        if (!$this->framework->isInitialized()) {
            return;
        }

        if ($this->queueIndexing && null !== ($news = NewsModel::findByAlias(Input::get('auto_item', false, true)))) {
            /** @var NewsCategoryModel $categories */
            if (null !== ($categories = NewsCategoryModel::findPublishedByNews($news->id))) {
                $relation = Relations::getRelation('tl_search', 'news_categories');
                $table = $relation['table'];
                $referenceField = $relation['reference_field'];
                $relatedField = $relation['related_field'];
                $url = Environment::get('base').Environment::get('relativeRequest');
                $searchEntry = $this->db->executeQuery('SELECT `id` FROM `tl_search` WHERE `url` = ? LIMIT 1', [$url])->fetch();

                if (false !== $searchEntry) {
                    $this->db->executeQuery("DELETE FROM `$table` WHERE `$referenceField` = ?", [(int) $searchEntry['id']]);
                    $this->db->executeQuery("UPDATE `tl_search` SET `newsId` = ? WHERE `id` = ?", [(int) $news->id, (int) $searchEntry['id']]);

                    foreach ($categories as $category) {
                        $this->db->executeQuery("INSERT INTO `$table` SET `$referenceField` = ?, `$relatedField` = ?", [
                            (int) $searchEntry['id'],
                            (int) $category->id,
                        ]);
                    }
                }
            }

            $this->queueIndexing = false;
        }
    }

    public function reset(): void
    {
        $this->queueIndexing = false;
    }
}
