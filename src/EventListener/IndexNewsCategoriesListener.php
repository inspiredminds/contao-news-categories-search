<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle\EventListener;

use Codefog\HasteBundle\DcaRelationsManager;
use Codefog\NewsCategoriesBundle\Model\NewsCategoryModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Module;
use Contao\ModuleNewsReader;
use Contao\NewsModel;
use Doctrine\DBAL\Connection;
use Haste\Model\Relations;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Contracts\Service\ResetInterface;

class IndexNewsCategoriesListener implements ResetInterface
{
    private static bool $queueIndexing = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContaoFramework $framework,
        private readonly Connection $db,
        private readonly LoggerInterface $logger,
        private readonly DcaRelationsManager|null $dcaRelationsManager = null,
    ) {
    }

    #[AsHook('parseArticles')]
    public function onParseArticles(FrontendTemplate $template, array $newsEntry, Module $module): void
    {
        if ($module instanceof ModuleNewsReader) {
            self::$queueIndexing = true;
        }
    }

    #[AsEventListener('kernel.terminate')]
    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$this->framework->isInitialized() || !$event->isMainRequest()) {
            return;
        }

        if (!self::$queueIndexing) {
            return;
        }

        $this->queueIndexing = false;

        if (!$news = NewsModel::findByAlias(Input::get('auto_item', false, true))) {
            return;
        }

        if (!$categories = NewsCategoryModel::findPublishedByNews($news->id)) {
            return;
        }

        if (class_exists(Relations::class)) {
            $relation = Relations::getRelation('tl_search', 'news_categories');
        } else {
            $relation = $this->dcaRelationsManager->getRelation('tl_search', 'news_categories');
        }

        $table = $relation['table'];
        $referenceField = $relation['reference_field'];
        $relatedField = $relation['related_field'];
        $url = Environment::get('base').Environment::get('relativeRequest');

        if (!$searchEntry = $this->db->fetchAssociative('SELECT `id` FROM `tl_search` WHERE `url` = ? LIMIT 1', [$url])) {
            return;
        }

        $this->db->executeQuery("DELETE FROM `$table` WHERE `$referenceField` = ?", [(int) $searchEntry['id']]);
        $this->db->executeQuery('UPDATE `tl_search` SET `newsId` = ? WHERE `id` = ?', [(int) $news->id, (int) $searchEntry['id']]);

        foreach ($categories as $category) {
            $this->db->executeQuery(
                "INSERT INTO `$table` SET `$referenceField` = ?, `$relatedField` = ?",
                [
                    (int) $searchEntry['id'],
                    (int) $category->id,
                ],
            );
        }
    }

    public function reset(): void
    {
        self::$queueIndexing = false;
    }
}
