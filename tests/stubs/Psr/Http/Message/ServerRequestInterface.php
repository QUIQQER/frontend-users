<?php

namespace Psr\Http\Message;

if (!interface_exists(ServerRequestInterface::class)) {
    interface ServerRequestInterface extends MessageInterface
    {
        public function getParsedBody(): mixed;

        public function withParsedBody(mixed $data): ServerRequestInterface;
    }
}
