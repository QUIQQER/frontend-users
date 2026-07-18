<?php

namespace Slim\Factory;

use Slim\App;

if (!class_exists(AppFactory::class)) {
    class AppFactory
    {
        public static function create(): App
        {
            return new App();
        }
    }
}
