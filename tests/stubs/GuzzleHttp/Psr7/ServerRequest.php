<?php

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\ServerRequestInterface;

if (!class_exists(ServerRequest::class)) {
    class ServerRequest implements ServerRequestInterface
    {
        private mixed $parsedBody = null;

        public function __construct(string $method, string $uri)
        {
        }

        public function getParsedBody(): mixed
        {
            return $this->parsedBody;
        }

        public function withParsedBody(mixed $data): ServerRequestInterface
        {
            $Clone = clone $this;
            $Clone->parsedBody = $data;

            return $Clone;
        }
    }
}
