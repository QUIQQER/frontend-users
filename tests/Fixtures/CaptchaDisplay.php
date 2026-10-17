<?php

namespace QUI\FrontendUsers\Tests\Fixtures;

use QUI;

class CaptchaDisplay extends QUI\Control
{
    public function getBody(): string
    {
        return '<span>captcha-test-challenge</span>';
    }
}
