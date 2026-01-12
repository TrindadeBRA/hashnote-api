<?php

declare(strict_types=1);

namespace HashNote\App;

use DI\Container;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

class AppFactory
{
    public static function create(Container $container): App
    {
        SlimAppFactory::setContainer($container);
        return SlimAppFactory::create();
    }
}

