<?php

namespace Slim\Routing;

if (!class_exists(RouteCollector::class)) {
    class RouteCollector
    {
        /** @var list<object> */
        private array $routes = [];

        /** @param callable|array{class-string, string}|string $callable */
        public function addRoute(string $method, string $pattern, mixed $callable): void
        {
            $this->routes[] = (object)[
                'method' => $method,
                'pattern' => $pattern,
                'callable' => $callable
            ];
        }

        /** @return list<object> */
        public function getRoutes(): array
        {
            return $this->routes;
        }
    }
}
