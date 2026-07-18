<?php

namespace Psr\Http\Message;

if (!interface_exists(StreamInterface::class)) {
    interface StreamInterface
    {
        public function __toString(): string;
    }
}
