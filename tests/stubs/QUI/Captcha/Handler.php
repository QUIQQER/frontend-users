<?php

namespace QUI\Captcha;

use QUI\Control;

if (!class_exists(Handler::class)) {
    /** Analysis-only signatures for the optional CAPTCHA package. */
    class Handler
    {
        public static function getDefaultCaptchaModuleControl(): Control
        {
            throw new \LogicException('PHPStan stub must not be executed.');
        }

        public static function isResponseValid(string $response, ?string $module = null): bool
        {
            throw new \LogicException('PHPStan stub must not be executed.');
        }

        public static function requiresJavaScript(?string $module = null): bool
        {
            throw new \LogicException('PHPStan stub must not be executed.');
        }

        public static function isInvisible(?string $module = null): bool
        {
            throw new \LogicException('PHPStan stub must not be executed.');
        }
    }
}
