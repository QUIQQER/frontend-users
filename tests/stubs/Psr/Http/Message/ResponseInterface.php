<?php

namespace Psr\Http\Message;

if (!interface_exists(ResponseInterface::class)) {
    interface ResponseInterface extends MessageInterface
    {
        public function getStatusCode(): int;

        public function getBody(): StreamInterface;
    }
}
