<?php

namespace QUI\FrontendUsers\Tests\Integration;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\ActivationLinkVerification;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\EmailVerification;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Rest\RegistrationData;
use QUI\FrontendUsers\Rest\Routes\PostRegister;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Tests\Support\VerificationSiteFixture;
use QUI\Utils\Singleton;
use ReflectionProperty;

class RegistrationPolicyWorkflowTest extends DatabaseTestCase
{
    private array $instances;
    private array $events;
    private array $mails = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->events = QUI::getEvents()->getList();
        // Site-specific user-create hooks may require tables outside the package fixtures.
        foreach ($this->events['onUserCreate'] ?? [] as $event) {
            if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                QUI::getEvents()->removeEvent('onUserCreate', $event['callable']);
            }
        }

        $Property = new ReflectionProperty(Singleton::class, 'instances');
        $this->instances = $Property->getValue();
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendMail'])->getMock();
        $Handler->method('sendMail')->willReturnCallback(function (array $data, array $recipients): void {
            $this->mails[] = $recipients;
        });
        $Property->setValue(null, array_replace($this->instances, [Handler::class => $Handler]));

        $this->configureRegistrar(Handler::ACTIVATION_MODE_MANUAL);
        foreach (
            [
            'usernameInput' => Handler::USERNAME_INPUT_REQUIRED,
            'passwordInput' => Handler::PASSWORD_INPUT_DEFAULT,
            'fullnameInput' => Handler::FULLNAME_INPUT_FULLNAME_OPTIONAL,
            'addressInput' => 0,
            'useCaptcha' => 0,
            'termsOfUseRequired' => 0,
            'defaultGroups' => '',
            'forcePasswordReset' => 0,
            'sendInfoMailOnRegistrationTo' => '',
            'autoLoginOnActivation' => 0,
            'userWelcomeMail' => 0,
            'emailBlacklist' => '[]'
            ] as $key => $value
        ) {
            $this->setPackageConfig('registration', $key, $value);
        }

        VerificationSiteFixture::setUp();
    }

    protected function tearDown(): void
    {
        VerificationSiteFixture::tearDown();
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->instances);
        $this->restoreEvents($this->events);
        parent::tearDown();
    }

    public static function activationModes(): array
    {
        $cases = [];
        foreach (['rest', 'browser'] as $transport) {
            foreach (
                [
                Handler::ACTIVATION_MODE_MANUAL => [false, null],
                Handler::ACTIVATION_MODE_MAIL => [false, ActivationLinkVerification::class],
                Handler::ACTIVATION_MODE_AUTO => [true, null],
                Handler::ACTIVATION_MODE_AUTO_WITH_EMAIL_CONFIRM => [true, EmailVerification::class]
                ] as $mode => [$active, $verificationClass]
            ) {
                $cases[$transport . '-' . $mode] = [$transport, $mode, $active, $verificationClass];
            }
        }

        return $cases;
    }

    #[DataProvider('activationModes')]
    public function testActivationPolicyAndLifecycleAttributes(
        string $transport,
        string $mode,
        bool $active,
        ?string $verificationClass
    ): void {
        $this->configureRegistrar($mode);
        $Group = $this->createGroup();
        $this->setPackageConfig('registration', 'defaultGroups', $Group->getUUID());
        $this->setPackageConfig('registration', 'forcePasswordReset', 1);
        $data = $this->registrationData();

        if ($transport === 'rest') {
            $Response = PostRegister::call(
                (new ServerRequest('POST', '/frontend-users/register'))->withParsedBody($data),
                new Response(),
                []
            );
            self::assertSame(200, $Response->getStatusCode(), (string)$Response->getBody());
            self::assertSame(['message' => 'OK'], json_decode((string)$Response->getBody(), true));
            $User = QUI::getUsers()->getUserByName($data['username']);
        } else {
            $_POST = $data + ['registration' => 1];
            $Control = new Registration(['Registrar' => new Registrar()]);
            self::assertSame(
                $mode === Handler::ACTIVATION_MODE_MAIL
                    ? Handler::REGISTRATION_STATUS_PENDING
                    : Handler::REGISTRATION_STATUS_SUCCESS,
                $Control->register()
            );
            $User = $Control->getRegisteredUser();
            self::assertNotNull($User);
        }

        self::assertSame($active, $User->isActive());
        self::assertTrue($User->isInGroup($Group->getUUID()));
        self::assertTrue((bool)$User->getAttribute('quiqqer.set.new.password'));
        self::assertSame(Registrar::class, $User->getAttribute(Handler::USER_ATTR_REGISTRAR));
        $Project = QUI::getProjectManager()->getStandard();
        self::assertSame($Project->getName(), $User->getAttribute(Handler::USER_ATTR_REGISTRATION_PROJECT));
        self::assertSame($Project->getLang(), $User->getAttribute(Handler::USER_ATTR_REGISTRATION_PROJECT_LANG));
        self::assertSame(!$active, (bool)$User->getAttribute(Handler::USER_ATTR_USER_ACTIVATION_REQUIRED));

        $verifications = self::getConnection()->fetchAllAssociative(
            'SELECT verificationHandler, customData FROM '
            . QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName('quiqqer_verification_processes'))
            . ' WHERE identifier = :activation OR identifier = :confirmation',
            ['activation' => 'activate-' . $User->getUUID(), 'confirmation' => 'confirmemail-' . $User->getUUID()]
        );

        if ($verificationClass === null) {
            self::assertSame([], $verifications);
            self::assertSame([], $this->mails);
        } else {
            self::assertCount(1, $verifications);
            self::assertSame($verificationClass, $verifications[0]['verificationHandler']);
            self::assertSame([[$data['email']]], $this->mails);

            if ($mode === Handler::ACTIVATION_MODE_MAIL) {
                $customData = json_decode(QUI\Security\Encryption::decrypt($verifications[0]['customData']), true);
                self::assertSame((new Registrar())->getHash(), $customData['registrar']);
            }
        }
    }

    public static function rejectedPolicies(): array
    {
        return [
            'disabled registrar' => ['disabled'],
            'missing terms' => ['terms'],
            'missing captcha' => ['captcha'],
            'invalid captcha' => ['invalid-captcha'],
            'blacklisted email' => ['blacklist']
        ];
    }

    #[DataProvider('rejectedPolicies')]
    public function testRestPolicyRejectionCreatesNeitherUserNorVerificationNorMail(string $policy): void
    {
        $data = $this->registrationData();

        switch ($policy) {
            case 'disabled':
                $this->configureRegistrar(Handler::ACTIVATION_MODE_MAIL, false);
                break;
            case 'terms':
                $this->setPackageConfig('registration', 'termsOfUseRequired', 1);
                $data['termsOfUseAccepted'] = false;
                break;
            case 'captcha':
            case 'invalid-captcha':
                $this->setPackageConfig('registration', 'useCaptcha', 1);
                $data['captchaResponse'] = $policy === 'captcha' ? '' : 'invalid-policy-test-response';
                break;
            case 'blacklist':
                $this->setPackageConfig('registration', 'emailBlacklist', '["*@example.invalid"]');
                break;
        }

        $userCount = self::countRows(QUI\Users\Manager::table());
        $verificationCount = self::countRows(QUI::getDBTableName('quiqqer_verification_processes'));
        $Response = PostRegister::call(
            (new ServerRequest('POST', '/frontend-users/register'))->withParsedBody($data),
            new Response(),
            []
        );

        self::assertSame(400, $Response->getStatusCode(), (string)$Response->getBody());
        self::assertSame($userCount, self::countRows(QUI\Users\Manager::table()));
        self::assertFalse(QUI::getUsers()->usernameExists($data['username']));
        self::assertSame(
            $verificationCount,
            self::countRows(QUI::getDBTableName('quiqqer_verification_processes'))
        );
        self::assertSame([], $this->mails);
    }

    public function testRestRequiredFieldsExposeConfiguredPolicyInputsAndAcceptTerms(): void
    {
        $this->setPackageConfig('registration', 'termsOfUseRequired', 1);
        $this->setPackageConfig('registration', 'useCaptcha', 1);
        self::assertArrayHasKey('termsOfUseAccepted', RegistrationData::getRequiredFields());
        self::assertArrayHasKey('captchaResponse', RegistrationData::getRequiredFields());
        $this->setPackageConfig('registration', 'useCaptcha', 0);
        self::assertArrayNotHasKey('captchaResponse', RegistrationData::getRequiredFields());
        $Response = PostRegister::call(
            (new ServerRequest('POST', '/frontend-users/register'))->withParsedBody(
                $this->registrationData() + ['termsOfUseAccepted' => true]
            ),
            new Response(),
            []
        );
        self::assertSame(200, $Response->getStatusCode(), (string)$Response->getBody());
    }

    private static function countRows(string $table): int
    {
        return (int)self::getConnection()->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(QUI\Utils\Doctrine::quoteIdentifier($table))
            ->executeQuery()
            ->fetchOne();
    }

    private function configureRegistrar(string $mode, bool $active = true): void
    {
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode(Registrar::class) => ['active' => $active, 'activationMode' => $mode, 'displayPosition' => 1]
        ]));
    }

    private function registrationData(): array
    {
        $username = self::TEST_PREFIX . 'policy-' . bin2hex(random_bytes(4));

        return [
            'username' => $username,
            'email' => $username . '@example.invalid',
            'password' => 'phpunit-policy-password',
            'firstname' => 'Policy',
            'lastname' => 'Regression'
        ];
    }
}
