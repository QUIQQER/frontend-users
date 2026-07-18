<?php

namespace Slim\Routing;

if (!class_exists(RouteCollectorProxy::class)) {
    class RouteCollectorProxy
    {
        public function __construct(private RouteCollector $RouteCollector)
        {
        }

        /** @param callable|array{class-string, string}|string $callable */
        public function post(string $pattern, mixed $callable): void
        {
            $this->RouteCollector->addRoute('POST', $pattern, $callable);
        }

        /** @param callable|array{class-string, string}|string $callable */
        public function get(string $pattern, mixed $callable): void
        {
            $this->RouteCollector->addRoute('GET', $pattern, $callable);
        }
    }
}
