<?php

namespace QUI\FrontendUsers\Exception;

use QUI\FrontendUsers\Exception;

class UserAlreadyExistsException extends Exception
{
    private const DEFAULT_CODE = 50001;

    /**
     * @param string|array<int|string, mixed>|null $message
     * @param array<string, mixed> $context
     */
    public function __construct(
        string | array | null $message = null,
        int $code = self::DEFAULT_CODE,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $context, $previous);
    }
}
