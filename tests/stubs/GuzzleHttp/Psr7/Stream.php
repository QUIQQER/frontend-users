<?php

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

if (!class_exists(Stream::class)) {
    class Stream implements StreamInterface
    {
        public function __construct(private string $content = '')
        {
        }

        public function __toString(): string
        {
            return $this->content;
        }
    }
}
