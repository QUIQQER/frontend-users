<?php

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

if (!class_exists(Response::class)) {
    class Response implements ResponseInterface
    {
        private StreamInterface $Body;

        /**
         * @param array<string, string|string[]> $headers
         * @param StreamInterface|resource|string|null $body
         */
        public function __construct(
            private int $status = 200,
            array $headers = [],
            mixed $body = null,
            string $version = '1.1',
            ?string $reason = null
        ) {
            $this->Body = $body instanceof StreamInterface
                ? $body
                : new Stream((string)($body ?? ''));
        }

        public function getStatusCode(): int
        {
            return $this->status;
        }

        public function getBody(): StreamInterface
        {
            return $this->Body;
        }
    }
}
