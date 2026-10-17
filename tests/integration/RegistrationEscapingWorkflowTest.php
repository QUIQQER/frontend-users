<?php

namespace QUI\FrontendUsers\Tests\Integration;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Control;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use ReflectionProperty;

class RegistrationEscapingWorkflowTest extends DatabaseTestCase
{
    private const ADDRESS_FIELDS = [
        'company', 'salutation', 'firstname', 'lastname', 'street_no',
        'zip', 'city', 'phone', 'mobile', 'fax'
    ];

    protected function setUp(): void
    {
        parent::setUp();
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode(Registrar::class) => [
                'active' => true, 'activationMode' => Handler::ACTIVATION_MODE_MANUAL, 'displayPosition' => 1
            ]
        ]));
        foreach (
            [
            'usernameInput' => Handler::USERNAME_INPUT_REQUIRED,
            'passwordInput' => Handler::PASSWORD_INPUT_DEFAULT,
            'fullnameInput' => Handler::FULLNAME_INPUT_FULLNAME_OPTIONAL,
            'addressInput' => 1,
            'addressFields' => json_encode(array_fill_keys(
                [...self::ADDRESS_FIELDS, 'country'],
                ['show' => true, 'required' => false]
            )),
            'useCaptcha' => 0,
            'termsOfUseRequired' => 0,
            'emailBlacklist' => '[]'
            ] as $key => $value
        ) {
            $this->setPackageConfig('registration', $key, $value);
        }

        $_POST = [];
        $_REQUEST = [];
    }

    public static function renderingCases(): array
    {
        $cases = [];
        foreach (['post-error', 'control', 'address'] as $path) {
            foreach (
                [
                'element' => '"><script data-xss="injected">alert(1)</script><input value="',
                'attribute' => '" autofocus onfocus="alert(1)" data-xss="injected',
                'ordinary' => 'Müller & Söhne <Berlin> "Haus" O\'Brien'
                ] as $name => $value
            ) {
                $cases[$path . '-' . $name] = [$path, $value];
            }
        }

        return $cases;
    }

    #[DataProvider('renderingCases')]
    public function testRegistrationValuesRemainTextAndPasswordsAreNotReturned(string $path, string $value): void
    {
        $password = 'secret-registration-password-"><img src=x onerror=alert(1)>';
        $_POST = array_fill_keys([...self::ADDRESS_FIELDS, 'username', 'email', 'country'], $value);
        $_POST['password'] = $password;

        if ($path === 'post-error') {
            $Registration = new Registration(['registrars' => [Registrar::class]]);
            $_POST['registration'] = 1;
            $_POST['registration_id'] = (new ReflectionProperty(Registration::class, 'id'))->getValue($Registration);
            $_POST['registrar'] = (new Registrar())->getHash();
            $_REQUEST = $_POST;
            $html = $Registration->getBody();
            self::assertNull($Registration->getRegisteredUser());
            self::assertStringContainsString('content-message-error', $html);
        } else {
            $Control = new Control();
            $html = $path === 'address' ? $Control->renderAddress() : $Control->getBody();
        }

        $Document = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        try {
            self::assertTrue($Document->loadHTML('<?xml encoding="UTF-8">' . $html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $XPath = new DOMXPath($Document);

        self::assertSame(0, $XPath->query('//*[@data-xss or @onfocus or @onerror]')->length);
        self::assertStringNotContainsString('<script data-xss=', $html);
        self::assertStringNotContainsString('secret-registration-password-', $html);
        self::assertStringContainsString(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $html);

        $fields = $path === 'address' ? self::ADDRESS_FIELDS : [...self::ADDRESS_FIELDS, 'username', 'email'];
        foreach ($fields as $field) {
            $Inputs = $XPath->query('//input[@name="' . $field . '"]');
            self::assertCount(1, $Inputs, $field);
            $Input = $Inputs->item(0);
            self::assertInstanceOf(DOMElement::class, $Input);
            self::assertSame($value, $Input->getAttribute('value'), $field);
        }

        self::assertCount(1, $XPath->query('//select[@name="country"]'));
        self::assertSame(0, $XPath->query('//select[@name="country"]/option[@selected]')->length);
        if ($path !== 'address') {
            $Password = $XPath->query('//input[@name="password"]')->item(0);
            self::assertInstanceOf(DOMElement::class, $Password);
            self::assertSame('', $Password->getAttribute('value'));
        }
    }
}
