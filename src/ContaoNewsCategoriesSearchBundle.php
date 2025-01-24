<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoNewsCategoriesSearchBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ContaoNewsCategoriesSearchBundle extends Bundle
{
    public function getPath()
    {
        return \dirname(__DIR__);
    }
}
