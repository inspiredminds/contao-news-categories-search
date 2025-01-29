<?php

declare(strict_types=1);

use Contao\Rector\Set\SetList;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\FirstClassCallableRector;

return RectorConfig::configure()
    ->withSets([SetList::CONTAO])
    ->withPhpSets(php74: true, php81: false)
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/contao',
    ])
    ->withSkip([
        FirstClassCallableRector::class,
        __DIR__.'/src/Controller/FrontendModule/SearchModuleController.php',
    ])
    ->withParallel()
    ->withCache(sys_get_temp_dir().'/rector_cache')
;
