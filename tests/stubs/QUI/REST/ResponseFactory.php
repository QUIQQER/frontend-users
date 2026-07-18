<?php

namespace QUI\REST;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

if (!class_exists(ResponseFactory::class)) {
    class ResponseFactory
    {
        public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
        {
            return new Response($code);
        }
    }
}
