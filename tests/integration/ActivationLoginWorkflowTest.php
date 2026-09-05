<?php

namespace QUI\FrontendUsers\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\ActivationLinkVerification;
use QUI\FrontendUsers\ActivationLogin;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\Events;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\ProfileSecurity;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Rest\RegistrationData;
use QUI\FrontendUsers\Rest\Routes\PostRegister;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Tests\Support\VerificationSiteFixture;
use QUI\Utils\Singleton;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\VerificationRepository;
use QUI\Verification\Verifier;
use ReflectionMethod;
use ReflectionProperty;

class ActivationLoginWorkflowTest extends DatabaseTestCase
{
    private array $events;
    private array $instances;
    private QUI\Config $config;
    private int $logins = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = QUI::$Conf;
        QUI::$Conf = clone $this->config;
        QUI::$Conf->setValue('auth_settings', 'secondary_frontend', 0);
        $this->events = QUI::getEvents()->getList();
        foreach ($this->events['onUserCreate'] ?? [] as $event) {
            if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                QUI::getEvents()->removeEvent('onUserCreate', $event['callable']);
            }
        }
        QUI::getEvents()->addEvent('onQuiqqerFrontendUsersUserAutoLogin', function (): void {
            $this->logins++;
        });
        $Property = new ReflectionProperty(Singleton::class, 'instances');
        $this->instances = $Property->getValue();
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendMail'])->getMock();
        $Property->setValue(null, array_replace($this->instances, [Handler::class => $Handler]));
        foreach (
            [
            'usernameInput' => Handler::USERNAME_INPUT_REQUIRED,
            'passwordInput' => Handler::PASSWORD_INPUT_DEFAULT,
            'fullnameInput' => Handler::FULLNAME_INPUT_FULLNAME_OPTIONAL,
            'addressInput' => 0, 'useCaptcha' => 0, 'termsOfUseRequired' => 0,
            'defaultGroups' => '', 'sendInfoMailOnRegistrationTo' => '',
            'autoLoginOnActivation' => 1, 'userWelcomeMail' => 0, 'emailBlacklist' => '[]'
            ] as $key => $value
        ) {
            $this->setPackageConfig('registration', $key, $value);
        }
        $this->mode(Handler::ACTIVATION_MODE_MAIL);
        VerificationSiteFixture::setUp();
        $this->anonymous();
    }

    protected function tearDown(): void
    {
        VerificationSiteFixture::tearDown();
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->instances);
        (new ReflectionProperty(QUI\Events\Event::class, 'events'))->setValue(QUI::getEvents(), $this->events);
        QUI::$Conf = $this->config;
        parent::tearDown();
    }

    public static function unboundSessions(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'other-browser', 'wrong-nonce', 'wrong-user', 'expired', 'future', 'malformed', 'legacy-link', 'rest'
        ]);
    }

    #[DataProvider('unboundSessions')]
    public function testActivationDoesNotAuthenticateAnUnboundBrowser(string $case): void
    {
        $User = $this->register($case === 'rest');
        $binding = QUI::getSession()->get(ActivationLogin::SESSION_KEY);
        switch ($case) {
            case 'other-browser':
            case 'rest':
                $this->anonymous();
                break;
            case 'legacy-link':
                $User->setAttribute(ActivationLogin::USER_ATTRIBUTE, false);
                $User->save(QUI::getUsers()->getSystemUser());
                break;
            case 'wrong-nonce':
                $binding['nonce'] = bin2hex(random_bytes(32));
                break;
            case 'wrong-user':
                $binding['uuid'] = 'another-user';
                break;
            case 'expired':
                $binding['created'] = time() - ActivationLogin::VALID_DURATION - 1;
                break;
            case 'future':
                $binding['created'] = time() + 60;
                break;
            case 'malformed':
                $binding = ['uuid' => $User->getUUID(), 'nonce' => [], 'created' => (string)time()];
                break;
        }
        if (!in_array($case, ['other-browser', 'rest'], true)) {
            QUI::getSession()->set(ActivationLogin::SESSION_KEY, $binding);
        }
        $before = $this->authState();
        $this->activate($User);
        self::assertTrue($User->isActive());
        self::assertSame($before, $this->authState());
        self::assertSame(0, $this->logins);
        self::assertFalse((bool)$User->getAttribute(Handler::USER_ATTR_ACTIVATION_LOGIN_EXECUTED));
    }

    public function testOriginalRegistrationSessionCanLoginOnceWithoutClaimingMfaOrRecentAuth(): void
    {
        $User = $this->register();
        $binding = QUI::getSession()->get(ActivationLogin::SESSION_KEY);
        self::assertNotSame($binding['nonce'], $User->getAttribute(ActivationLogin::USER_ATTRIBUTE));
        $this->activate($User);
        self::assertSame($User->getUUID(), QUI::getSession()->get('uid'));
        self::assertSame(1, QUI::getSession()->get('auth'));
        self::assertSame(1, QUI::getSession()->get('auth-primary'));
        self::assertEmpty(QUI::getSession()->get('auth-secondary'));
        self::assertEmpty(QUI::getSession()->get(ProfileSecurity::RECENT_AUTH_SESSION_KEY));
        self::assertEmpty(QUI::getSession()->get(ActivationLogin::SESSION_KEY));
        self::assertFalse((bool)$User->getAttribute(ActivationLogin::USER_ATTRIBUTE));
        self::assertSame(1, $this->logins);
        $this->anonymous();
        QUI::getSession()->set(ActivationLogin::SESSION_KEY, $binding);
        Events::onUserActivate($User);
        self::assertEmpty(QUI::getSession()->get('uid'));
        self::assertSame(1, $this->logins);
    }

    public static function mfaModes(): array
    {
        return [[1], [2]];
    }

    #[DataProvider('mfaModes')]
    public function testActivationWithConfiguredMfaLeavesAuthenticationUntouched(int $mfa): void
    {
        $User = $this->register();
        QUI::$Conf->setValue('auth_settings', 'secondary_frontend', $mfa);
        $before = $this->authState();
        $this->activate($User);
        Events::autoLogin($User, false);
        self::assertTrue($User->isActive());
        self::assertSame($before, $this->authState());
        self::assertSame(0, $this->logins);
    }

    public function testActivationPreservesAnExistingDifferentLogin(): void
    {
        $User = $this->register();
        $Other = $this->createUser(true);
        self::replaceSessionUser($Other);
        QUI::getSession()->set('uid', $Other->getUUID());
        QUI::getSession()->set('auth', 1);
        QUI::getSession()->set('auth-primary', 1);
        QUI::getSession()->set('auth-secondary', 1);
        $before = $this->authState();
        $this->activate($User);
        self::assertSame($before, $this->authState());
        self::assertSame(0, $this->logins);
    }

    public function testResendCannotBindTheRequestingBrowser(): void
    {
        $User = $this->register();
        $hash = $User->getAttribute(ActivationLogin::USER_ATTRIBUTE);
        $this->anonymous();
        $Registrar = new Registrar();
        $Registrar->setProject(QUI::getRewrite()->getProject());
        self::assertTrue(Handler::getInstance()->sendActivationMail($User, $Registrar));
        self::assertEmpty(QUI::getSession()->get(ActivationLogin::SESSION_KEY));
        self::assertSame($hash, $User->getAttribute(ActivationLogin::USER_ATTRIBUTE));
        $this->activate($User);
        self::assertEmpty(QUI::getSession()->get('uid'));
        self::assertSame(0, $this->logins);
    }

    public function testResendPreservesTheOriginalBrowserBinding(): void
    {
        $User = $this->register();
        $binding = QUI::getSession()->get(ActivationLogin::SESSION_KEY);
        $Registrar = new Registrar();
        $Registrar->setProject(QUI::getRewrite()->getProject());
        self::assertTrue(Handler::getInstance()->sendActivationMail($User, $Registrar));
        self::assertSame($binding, QUI::getSession()->get(ActivationLogin::SESSION_KEY));
        $this->activate($User);
        self::assertSame($User->getUUID(), QUI::getSession()->get('uid'));
        self::assertSame(1, $this->logins);
    }

    public function testDisabledAutoLoginDoesNotBindOrAuthenticate(): void
    {
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 0);
        $User = $this->register();
        self::assertEmpty(QUI::getSession()->get(ActivationLogin::SESSION_KEY));
        $before = $this->authState();
        $this->activate($User);
        self::assertSame($before, $this->authState());
        self::assertSame(0, $this->logins);
    }

    public function testManualActivationDoesNotAutomaticallyLogin(): void
    {
        $this->mode(Handler::ACTIVATION_MODE_MANUAL);
        $User = $this->register();
        $before = $this->authState();
        $User->activate('', QUI::getUsers()->getSystemUser());
        self::assertSame($before, $this->authState());
        self::assertSame(0, $this->logins);
    }

    public function testExplicitTrustedAutoLoginRemainsAvailableWithoutMfa(): void
    {
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 0);
        $User = $this->createUser(true);
        Events::autoLogin($User, false);
        self::assertSame($User->getUUID(), QUI::getSession()->get('uid'));
        self::assertSame(1, QUI::getSession()->get('auth'));
        self::assertSame(1, $this->logins);
        self::assertEmpty(QUI::getSession()->get('auth-secondary'));
    }

    public static function immediateModes(): array
    {
        return [[Handler::ACTIVATION_MODE_AUTO], [Handler::ACTIVATION_MODE_AUTO_WITH_EMAIL_CONFIRM]];
    }

    #[DataProvider('immediateModes')]
    public function testImmediateBrowserActivationRetainsAutomaticLogin(string $mode): void
    {
        $this->mode($mode);
        $User = $this->register();
        self::assertTrue($User->isActive());
        self::assertSame($User->getUUID(), QUI::getSession()->get('uid'));
        self::assertSame(1, $this->logins);
        self::assertEmpty(QUI::getSession()->get('auth-secondary'));
    }

    public function testRenderedRegistrationWithMfaDoesNotPreauthenticateTheSession(): void
    {
        $this->mode(Handler::ACTIVATION_MODE_AUTO);
        QUI::$Conf->setValue('auth_settings', 'secondary_frontend', 1);
        $_POST = $this->data() + ['registration' => 1];
        $before = $this->authState();
        $Control = new Registration(['Registrar' => new Registrar(), 'async' => true]);
        $Control->getBody();
        self::assertTrue($Control->getRegisteredUser()->isActive());
        self::assertSame($before, $this->authState());
        self::assertSame(0, $this->logins);
    }

    private function activate(QUI\Users\User $User): void
    {
        $verification = (new VerificationRepository())->findByIdentifier('activate-' . $User->getUUID());
        self::assertInstanceOf(LinkVerification::class, $verification);
        $binding = QUI::getSession()->get(ActivationLogin::SESSION_KEY);
        if (is_array($binding) && is_string($binding['nonce'] ?? null)) {
            self::assertStringNotContainsString($binding['nonce'], json_encode($verification->customData));
            self::assertStringNotContainsString($binding['nonce'], $verification->getVerificationUrl());
        }
        QUI::getRequest()->setMethod('GET');
        (new Verifier())->verifyVerificationCode($verification, $verification->verificationCode);
        (new ActivationLinkVerification())->onSuccess($verification);
    }

    private function register(bool $rest = false): QUI\Users\User
    {
        $data = $this->data();
        if ($rest) {
            $Data = new RegistrationData();
            $Data->setAttributes($data);
            (new ReflectionMethod(PostRegister::class, 'registerUser'))->invoke(null, $Data);
        } else {
            $_POST = $data + ['registration' => 1];
            (new Registration(['Registrar' => new Registrar()]))->register();
        }
        return QUI::getUsers()->getUserByName($data['username']);
    }

    private function data(): array
    {
        $username = self::TEST_PREFIX . bin2hex(random_bytes(4));
        return ['username' => $username, 'email' => $username . '@example.invalid',
            'password' => 'phpunit-activation-password', 'firstname' => 'Activation', 'lastname' => 'Test'];
    }

    private function mode(string $mode): void
    {
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode(Registrar::class) => ['active' => true, 'activationMode' => $mode, 'displayPosition' => 1]
        ]));
    }

    private function anonymous(): void
    {
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        foreach (array_keys($this->authState()) as $key) {
            QUI::getSession()->remove($key);
        }
        QUI::getSession()->remove(ActivationLogin::SESSION_KEY);
    }

    private function authState(): array
    {
        $state = [];
        foreach (['uid', 'auth', 'auth-primary', 'auth-secondary', 'secHash', ProfileSecurity::RECENT_AUTH_SESSION_KEY] as $key) {
            $state[$key] = QUI::getSession()->get($key);
        }
        return $state;
    }
}
