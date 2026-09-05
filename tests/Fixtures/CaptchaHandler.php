<?php

namespace QUI\FrontendUsers\Tests\Fixtures;

use QUI;

/** Controlled optional CAPTCHA provider, loaded only in isolated CAPTCHA tests. */
class CaptchaHandler
{
    public static bool $available = true;
    public static int $validations = 0;

    public static function getDefaultCaptchaModuleControl(): QUI\Control
    {
        if (!self::$available) {
            throw new QUI\Exception('No configured CAPTCHA module.');
        }

        return new QUI\Control();
    }

    public static function isResponseValid(string $response): bool
    {
        self::$validations++;
        return $response === 'verified-test-challenge';
    }

    public static function requiresJavaScript(): bool
    {
        return false;
    }

    public static function isInvisible(): bool
    {
        return false;
    }
}
