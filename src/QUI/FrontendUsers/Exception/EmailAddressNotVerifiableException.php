<?php

namespace QUI\FrontendUsers\Exception;

use QUI\FrontendUsers\Exception;

class EmailAddressNotVerifiableException extends Exception
{
    private const DEFAULT_CODE = 50002;

    /**
     * Exception::$code is untyped and must not be redeclared with a native type.
     * The default code is therefore passed to the parent constructor.
     *
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
