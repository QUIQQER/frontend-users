<?php

namespace QUI\FrontendUsers\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\ActivationLookup;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\RegistrationThrottle;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Tests\Support\VerificationSiteFixture;
use QUI\Users\Auth\QUIQQER as PasswordAuthenticator;
use QUI\Utils\Singleton;
use QUI\Verification\Enum\VerificationStatus;
use QUI\Verification\VerificationRepository;
use ReflectionProperty;

class AccountLookupWorkflowTest extends DatabaseTestCase
{
    private array $events;
    private array $instances;
    private array $callables;
    private QUI\Config $config;
    private array $sessionValues = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = QUI::$Conf;
        QUI::$Conf = clone $this->config;
        QUI::$Conf->setValue('auth_settings', 'secondary_frontend', 0);
        foreach (array_keys(QUI::conf('auth_frontend') ?: []) as $authenticator) {
            QUI::$Conf->setValue('auth_frontend', $authenticator, 0);
        }
        QUI::$Conf->setValue('auth_frontend', PasswordAuthenticator::class, 1);
        $this->events = QUI::getEvents()->getList();
        foreach (['onUserCreate', 'onUserLoginError', 'onUserLogin', 'onUserLoginStart', 'onUserAuthenticatorLoginStart'] as $name) {
            foreach ($this->events[$name] ?? [] as $event) {
                if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                    QUI::getEvents()->removeEvent($name, $event['callable']);
                }
            }
        }
        QUI::getEvents()->addEvent('onAjaxCallBefore', [ActivationLookup::class, 'onAjaxCallBefore']);
        QUI::getEvents()->addEvent('onUserLoginError', [ActivationLookup::class, 'onUserLoginError']);
        $this->callables = QUI::getAjax()->getRegisteredCallables();
        require OPT_DIR . 'quiqqer/core/admin/ajax/users/login.php';
        $Property = new ReflectionProperty(Singleton::class, 'instances');
        $this->instances = $Property->getValue();
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendMail'])->getMock();
        $Property->setValue(null, array_replace($this->instances, [Handler::class => $Handler]));
        VerificationSiteFixture::setUp();
        $this->setPackageConfig('registration', 'throttleLookupIpLimit', 60);
        $this->setPackageConfig('registration', 'userWelcomeMail', 0);
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 0);
        foreach ([ActivationLookup::SESSION_KEY, 'auth-' . PasswordAuthenticator::class] as $key) {
            $this->sessionValues[$key] = QUI::getSession()->get($key);
        }
        $this->anonymous();
    }

    protected function tearDown(): void
    {
        VerificationSiteFixture::tearDown();
        foreach ($this->sessionValues as $key => $value) {
            QUI::getSession()->set($key, $value);
        }
        (new ReflectionProperty(QUI\Ajax::class, 'callables'))->setValue(null, $this->callables);
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->instances);
        (new ReflectionProperty(QUI\Events\Event::class, 'events'))->setValue(QUI::getEvents(), $this->events);
        QUI::$Conf = $this->config;
        parent::tearDown();
    }

    public function testRealPasswordLoginPreservesProofAfterCoreClearsTheSession(): void
    {
        $User = $this->pendingUser();
        self::assertFalse($this->lookup('activation', $User->getUUID())['result']);
        $oldSession = QUI::getSession()->getId();
        $response = $this->login($User->getUsername(), 'phpunit-lookup-password');
        self::assertSame('auth_error_user_not_active', $response['Exception']['attributes']['reason'] ?? $response['Exception']['reason'] ?? null, json_encode($response));
        self::assertNotSame($oldSession, QUI::getSession()->getId());
        self::assertFalse((bool)QUI::getSession()->get('auth'));
        self::assertFalse((bool)QUI::getSession()->get('auth-primary'));
        self::assertSame($User->getUUID(), QUI::getSession()->get(ActivationLookup::SESSION_KEY)['uuid'] ?? null);
        self::assertSame($User->getAttribute('email'), $this->lookup('activation', $User->getUUID())['result']);
        self::assertSame($User->getAttribute('email'), $this->lookup('activation', $User->getId())['result']);
        self::assertFalse($this->lookup('activation', 'unknown')['result']);
    }

    public function testWrongPasswordAndUnknownUserHaveTheSamePublicFailureWithoutProof(): void
    {
        $User = $this->pendingUser();
        $known = $this->login($User->getUsername(), 'incorrect');
        self::assertFalse((bool)QUI::getSession()->get(ActivationLookup::SESSION_KEY));
        $this->anonymous();
        $unknown = $this->login('phpunit-lookup-unknown', 'incorrect');
        self::assertSame($known['Exception']['message'], $unknown['Exception']['message']);
        self::assertSame(401, $known['Exception']['code']);
        self::assertSame(401, $unknown['Exception']['code']);
        self::assertFalse($this->lookup('activation', $User->getUUID())['result']);
    }

    public function testLookupQuotaIsSharedAndSurvivesSessionDeletion(): void
    {
        $User = $this->pendingUser();
        $this->setPackageConfig('registration', 'throttleLookupIpLimit', 3);
        self::assertTrue($this->lookup('username', $User->getUsername())['result']);
        self::assertFalse($this->lookup('email', 'unknown@example.invalid')['result']);
        self::assertFalse($this->lookup('activation', $User->getUUID())['result']);
        $before = $this->counters();
        QUI::getSession()->destroy();
        QUI::getSession()->start();
        $this->anonymous();
        foreach (['username', 'email', 'activation'] as $kind) {
            self::assertSame(429, $this->lookup($kind, 'unknown')['Exception']['code']);
        }
        self::assertSame($before, $this->counters());
        RegistrationThrottle::reserve('registration@example.invalid', 'registration');
        QUI::getRequest()->server->set('REMOTE_ADDR', '192.0.2.2');
        self::assertTrue($this->lookup('email', $User->getAttribute('email'))['result']);
    }

    public static function deniedProofs(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'other-user', 'other-session', 'expired', 'future', 'malformed', 'registration-flags',
            'subsequent-failed-login', 'expired-verification', 'verified', 'missing', 'active',
            'wrong-handler', 'wrong-verification-user', 'deleted', 'expired-status'
        ]);
    }

    #[DataProvider('deniedProofs')]
    public function testUnsafeActivationLookupsReturnFalse(string $case): void
    {
        $User = $this->pendingUser();
        $this->login($User->getUsername(), 'phpunit-lookup-password');
        self::assertIsArray(QUI::getSession()->get(ActivationLookup::SESSION_KEY));
        $Repository = new VerificationRepository();
        $verification = $Repository->findByIdentifier('activate-' . $User->getUUID());
        self::assertNotNull($verification);
        $table = QUI::getDBTableName(VerificationRepository::TBL_VERIFICATION_PROCESSES);
        $id = $User->getUUID();

        switch ($case) {
            case 'other-user':
                $id = $this->pendingUser()->getUUID();
                break;
            case 'other-session':
                QUI::getSession()->destroy();
                QUI::getSession()->start();
                break;
            case 'expired':
            case 'future':
                QUI::getSession()->set(ActivationLookup::SESSION_KEY, [
                    'uuid' => $id,
                    'time' => time() + ($case === 'future' ? 60 : -ActivationLookup::DURATION)
                ]);
                break;
            case 'malformed':
                QUI::getSession()->set(ActivationLookup::SESSION_KEY, ['uuid' => $id, 'time' => (string)time()]);
                break;
            case 'registration-flags':
                $this->anonymous();
                QUI::getSession()->set('uid', $id);
                QUI::getSession()->set('auth-primary', 1);
                QUI::getSession()->set('inAuthentication', 1);
                break;
            case 'subsequent-failed-login':
                $this->login('unknown@example.invalid', 'incorrect');
                break;
            case 'expired-verification':
                self::getConnection()->update($table, ['validUntilDate' => '2000-01-01 00:00:00'], ['uuid' => $verification->uuid]);
                break;
            case 'verified':
            case 'expired-status':
                $verification->status = $case === 'verified' ? VerificationStatus::VERIFIED : VerificationStatus::EXPIRED;
                $Repository->update($verification);
                break;
            case 'missing':
                $Repository->delete($verification);
                break;
            case 'active':
            case 'deleted':
                self::getConnection()->update(QUI\Users\Manager::table(), ['active' => $case === 'active' ? 1 : -1], ['uuid' => $id]);
                $User->refresh();
                break;
            case 'wrong-handler':
                self::getConnection()->update($table, [
                    'verificationHandler' => QUI\FrontendUsers\EmailConfirmLinkVerification::class
                ], ['uuid' => $verification->uuid]);
                break;
            case 'wrong-verification-user':
                $verification->customData['uuid'] = 'another-user';
                $Repository->delete($verification);
                $Repository->insert($verification, new QUI\FrontendUsers\ActivationLinkVerification());
                break;
        }

        self::assertFalse($this->lookup('activation', $id)['result']);
    }

    public function testActivationLookupsDoNotExtendTheProofAndKeepResendAvailable(): void
    {
        $User = $this->pendingUser();
        $this->login($User->getUsername(), 'phpunit-lookup-password');
        $proof = QUI::getSession()->get(ActivationLookup::SESSION_KEY);
        $proof['time'] -= 100;
        QUI::getSession()->set(ActivationLookup::SESSION_KEY, $proof);
        self::assertSame($User->getAttribute('email'), $this->lookup('activation', $User->getUUID())['result']);
        self::assertSame($proof, QUI::getSession()->get(ActivationLookup::SESSION_KEY));
        require dirname(__DIR__, 2) . '/ajax/frontend/auth/resendActivationMail.php';
        $response = QUI::getAjax()->callRequestFunction('package_quiqqer_frontend-users_ajax_frontend_auth_resendActivationMail', [
            'email' => $User->getAttribute('email')
        ]);
        self::assertTrue($response['result']);
        self::assertSame($proof, QUI::getSession()->get(ActivationLookup::SESSION_KEY));
        self::assertFalse((bool)QUI::getSession()->get('auth'));
    }

    public function testCachedAuthenticationCannotCreateOrRenewPasswordProof(): void
    {
        $User = $this->pendingUser();
        QUI::getSession()->set('uid', $User->getUUID());
        QUI::getSession()->set('username', $User->getUsername());
        QUI::getSession()->set('auth-' . PasswordAuthenticator::class, 1);
        $this->login($User->getUsername(), 'incorrect');
        self::assertFalse($this->lookup('activation', $User->getUUID())['result']);
    }

    public function testPrimaryPasswordProofDoesNotCompleteRequiredMfa(): void
    {
        $User = $this->pendingUser();
        QUI::$Conf->setValue('auth_settings', 'secondary_frontend', 1);
        $this->login($User->getUsername(), 'phpunit-lookup-password');
        self::assertFalse((bool)QUI::getSession()->get('auth'));
        self::assertNotSame(1, QUI::getSession()->get('auth-secondary'));
        self::assertSame($User->getAttribute('email'), $this->lookup('activation', $User->getUUID())['result']);
    }

    public static function invalidLimits(): array
    {
        return [[null], [''], [0], [-1], ['invalid']];
    }

    #[DataProvider('invalidLimits')]
    public function testInvalidLimitsUseSixtyRequestsAndExpiryDoesNotSlide(mixed $limit): void
    {
        if ($limit === null) {
            $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['getRegistrationSettings'])->getMock();
            $Handler->method('getRegistrationSettings')->willReturn([]);
            (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, array_replace(
                $this->instances,
                [Handler::class => $Handler]
            ));
        } else {
            $this->setPackageConfig('registration', 'throttleLookupIpLimit', $limit);
        }
        for ($i = 0; $i < 60; $i++) {
            self::assertFalse($this->lookup('email', 'invalid')['result']);
        }
        $before = $this->counters();
        self::assertCount(1, $before);
        self::assertSame(429, $this->lookup('username', 'unknown')['Exception']['code']);
        self::assertSame($before, $this->counters());
        self::getConnection()->update(RegistrationThrottle::table(), ['expires_at' => time() - 1], [
            'subject_key' => $before[0]['subject_key']
        ]);
        self::assertFalse($this->lookup('username', 'unknown')['result']);
        self::assertSame(1, (int)$this->counters()[0]['attempts']);
    }

    public static function equivalentSources(): array
    {
        return [
            ['192.0.2.1', '::ffff:192.0.2.1'],
            ['2001:db8::1', '2001:0db8:0000:0000:0000:0000:0000:0001']
        ];
    }

    #[DataProvider('equivalentSources')]
    public function testEquivalentAddressesAndForgedForwardingShareQuota(string $first, string $second): void
    {
        $this->setPackageConfig('registration', 'throttleLookupIpLimit', 1);
        QUI::getRequest()->server->set('REMOTE_ADDR', $first);
        self::assertFalse($this->lookup('username', 'unknown')['result']);
        QUI::getRequest()->server->set('REMOTE_ADDR', $second);
        QUI::getRequest()->server->set('HTTP_X_FORWARDED_FOR', '198.51.100.1');
        self::assertSame(429, $this->lookup('email', 'unknown@example.invalid')['Exception']['code']);
    }

    public function testMissingSourceAndUnavailableStorageDoNotReturnAccountData(): void
    {
        $User = $this->pendingUser();
        QUI::getRequest()->server->remove('REMOTE_ADDR');
        self::assertSame(429, $this->lookup('username', $User->getUsername())['Exception']['code']);
        QUI::getRequest()->server->set('REMOTE_ADDR', '192.0.2.1');
        self::getConnection()->createSchemaManager()->dropTable(RegistrationThrottle::table());
        self::assertArrayNotHasKey('result', $this->lookup('email', $User->getAttribute('email')));
    }

    private function pendingUser(): QUI\Users\User
    {
        $User = $this->createUser();
        $User->setPassword('phpunit-lookup-password', QUI::getUsers()->getSystemUser());
        $Registrar = new Registrar();
        $Registrar->setProject(QUI::getRewrite()->getProject());
        self::assertTrue(Handler::getInstance()->sendActivationMail($User, $Registrar));
        return $User;
    }

    private function anonymous(): void
    {
        foreach (
            ['uid', 'username', 'auth', 'auth-primary', 'auth-secondary', 'inAuthentication',
            'auth-' . PasswordAuthenticator::class, ActivationLookup::SESSION_KEY] as $key
        ) {
            QUI::getSession()->remove($key);
        }
        self::replaceSessionUser(QUI::getUsers()->getNobody());
    }

    private function login(string $username, string $password): array
    {
        return QUI::getAjax()->callRequestFunction('ajax_users_login', [
            'authenticator' => PasswordAuthenticator::class,
            'params' => json_encode(['username' => $username, 'password' => $password]),
            'authStep' => 'primary', 'authenticators' => [PasswordAuthenticator::class]
        ]);
    }

    private function lookup(string $kind, mixed $value): array
    {
        [$path, $param] = match ($kind) {
            'username' => ['registrars/userExists', 'username'],
            'email' => ['registrars/emailExists', 'email'],
            'activation' => ['auth/existsUnverifiedActivation', 'userId']
        };
        require dirname(__DIR__, 2) . '/ajax/frontend/' . $path . '.php';
        $name = 'package_quiqqer_frontend-users_ajax_frontend_' . str_replace('/', '_', $path);
        return QUI::getAjax()->callRequestFunction($name, [$param => $value]);
    }

    private function counters(): array
    {
        return self::getConnection()->fetchAllAssociative('SELECT * FROM ' . RegistrationThrottle::table());
    }
}
