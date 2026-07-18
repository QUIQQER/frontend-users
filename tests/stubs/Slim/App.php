<?php

namespace Slim;

use Slim\Routing\RouteCollector;
use Slim\Routing\RouteCollectorProxy;

if (!class_exists(App::class)) {
    class App
    {
        private RouteCollector $RouteCollector;

        public function __construct()
        {
            $this->RouteCollector = new RouteCollector();
        }

        public function group(string $pattern, callable $callable): void
        {
            $callable(new RouteCollectorProxy($this->RouteCollector));
        }

        public function getRouteCollector(): RouteCollector
        {
            return $this->RouteCollector;
        }
    }
}
