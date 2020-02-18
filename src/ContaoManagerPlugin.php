<?php

declare(strict_types=1);

/*
 * This file is part of the ContaoNewsCategoriesSearchBundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle;

use Codefog\NewsCategoriesBundle\CodefogNewsCategoriesBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\NewsBundle\ContaoNewsBundle;

/**
 * Plugin for the Contao Manager.
 */
class ContaoManagerPlugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(ContaoNewsCategoriesSearchBundle::class)
                ->setLoadAfter([
                    ContaoCoreBundle::class,
                    ContaoNewsBundle::class,
                    CodefogNewsCategoriesBundle::class,
                    'haste',
                ]),
        ];
    }
}
