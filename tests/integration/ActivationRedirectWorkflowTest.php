<?php

namespace QUI\FrontendUsers\Tests\Integration;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\Controls\RegistrationSignUp;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;

class ActivationRedirectWorkflowTest extends DatabaseTestCase
{
    public static function emailInputs(): array
    {
        return [
            'redirect-without-email' => [''],
            'legacy-prefill' => ['activation+private@example.invalid']
        ];
    }

    #[DataProvider('emailInputs')]
    public function testExpiredActivationAllowsEmailEntryWithoutAnEmailInTheRedirect(string $email): void
    {
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $this->setPackageConfig('registration', 'useCaptcha', 0);
        $_POST = [];
        $_GET = ['error' => 'activation_expired'];

        if ($email !== '') {
            $_GET['email'] = $email;
        }

        $_REQUEST = $_GET;
        $html = (new RegistrationSignUp())->getBody();
        $Document = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);

        try {
            self::assertTrue($Document->loadHTML('<?xml encoding="UTF-8">' . $html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        $XPath = new DOMXPath($Document);
        self::assertCount(1, $XPath->query('//*[@data-name="resend"]'));
        $Input = $XPath->query('//label/input[@data-name="resend-email"]')->item(0);
        self::assertInstanceOf(DOMElement::class, $Input);
        self::assertSame('email', $Input->getAttribute('type'));
        self::assertSame($email, $Input->getAttribute('value'));
        self::assertTrue($Input->hasAttribute('required'));
        self::assertCount(1, $XPath->query('//*[@data-name="resend-button"]'));
        self::assertCount(1, $XPath->query('//*[@data-name="resend-message" and @aria-live="polite"]'));
    }
}
