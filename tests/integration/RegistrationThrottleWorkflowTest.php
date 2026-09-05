<?php

namespace QUI\FrontendUsers\Tests\Integration;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\RegistrationThrottle;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Rest\RegistrationData;
use QUI\FrontendUsers\Rest\Routes\PostRegister;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Tests\Support\VerificationSiteFixture;
use QUI\Utils\Singleton;
use ReflectionProperty;

class RegistrationThrottleWorkflowTest extends DatabaseTestCase
{
    private array $instances;
    private array $events;
    private array $mails = [];
    private string $message = "";
    private bool $mailFailure = false;

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
            if ($this->mailFailure) {
                throw new \RuntimeException('Simulated registration mail failure');
            }
        });
        $Property->setValue(null, array_replace($this->instances, [Handler::class => $Handler]));

        $this->configureRegistrar(Handler::ACTIVATION_MODE_MANUAL);
        foreach (
            [
            'throttleIpLimit' => 20,
            'throttleIdentityLimit' => 5,
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
        self::replaceSessionUser(QUI::getUsers()->getNobody());
    }

    protected function tearDown(): void
    {
        VerificationSiteFixture::tearDown();
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->instances);
        (new ReflectionProperty(QUI\Events\Event::class, 'events'))->setValue(QUI::getEvents(), $this->events);
        parent::tearDown();
    }

    public static function transportsAndModes(): array
    {
        $cases = [];
        foreach (['browser', 'ajax', 'rest'] as $transport) {
            foreach ([Handler::ACTIVATION_MODE_AUTO, Handler::ACTIVATION_MODE_MAIL] as $mode) {
                $cases[$transport . '-' . $mode] = [$transport, $mode];
            }
        }
        return $cases;
    }

    #[DataProvider('transportsAndModes')]
    public function testIpBurstRejectsBeforeAnySideEffects(string $transport, string $mode): void
    {
        $this->configureRegistrar($mode);
        $this->setPackageConfig('registration', 'throttleIpLimit', 2);
        $Group = $this->createGroup();
        $this->setPackageConfig('registration', 'defaultGroups', $Group->getUUID());
        $this->setPackageConfig('registration', 'sendInfoMailOnRegistrationTo', 'admin@example.invalid');
        for ($i = 0; $i < 2; $i++) {
            $data = $this->registrationData();
            self::assertTrue($this->attempt($transport, $data), $this->message);
            $User = QUI::getUsers()->getUserByName($data['username']);
            self::assertTrue($User->isInGroup($Group->getUUID()));
            self::assertSame($mode === Handler::ACTIVATION_MODE_AUTO, $User->isActive());
        }
        self::assertCount($mode === Handler::ACTIVATION_MODE_MAIL ? 4 : 2, $this->mails);
        $before = $this->snapshot();
        self::assertFalse($this->attempt($transport, $this->registrationData()));
        self::assertStringContainsString($this->throttleMessage(), $this->message);
        self::assertSame($before, $this->snapshot());
        // Another source and identity have their own quota, with no global cap.
        QUI::getRequest()->server->set('REMOTE_ADDR', '192.0.2.2');
        self::assertTrue($this->attempt($transport, $this->registrationData()), $this->message);
    }

    public function testInvalidAttemptsShareTheIpQuotaAcrossTransports(): void
    {
        $this->setPackageConfig('registration', 'throttleIpLimit', 2);
        $before = $this->snapshot();
        foreach (['browser', 'rest'] as $transport) {
            $data = $this->registrationData();
            $data['email'] = 'invalid';
            self::assertFalse($this->attempt($transport, $data));
            self::assertNotSame($this->throttleMessage(), $this->message);
        }
        self::assertFalse($this->attempt('ajax', $this->registrationData()));
        self::assertStringContainsString($this->throttleMessage(), $this->message);
        self::assertSame($before, $this->snapshot());
    }

    public static function identities(): array
    {
        return [['email'], ['username']];
    }

    #[DataProvider('identities')]
    public function testIdentityQuotaNormalizesCaseAndWhitespaceAcrossSources(string $field): void
    {
        $this->setPackageConfig('registration', 'throttleIdentityLimit', 2);
        $this->setPackageConfig('registration', 'termsOfUseRequired', 1);
        $identity = $this->registrationData()[$field];
        $before = $this->snapshot();
        foreach (['browser', 'rest', 'browser'] as $i => $transport) {
            QUI::getRequest()->server->set('REMOTE_ADDR', '192.0.2.' . ($i + 1));
            $data = $this->registrationData();
            $data[$field] = $i === 1 ? ' ' . strtoupper($identity) . ' ' : $identity;
            self::assertFalse($this->attempt($transport, $data));
            if ($i < 2) {
                self::assertNotSame($this->throttleMessage(), $this->message);
            } else {
                self::assertSame($this->throttleMessage(), $this->message);
            }
        }
        self::assertSame($before, $this->snapshot());
    }

    public function testDefaultsAndExpiryDoNotExtendTheWindow(): void
    {
        $this->setPackageConfig('registration', 'throttleIpLimit', '');
        $this->setPackageConfig('registration', 'throttleIdentityLimit', '');
        for ($i = 0; $i < 20; $i++) {
            RegistrationThrottle::reserve('email' . $i, 'user' . $i);
        }
        $before = self::getConnection()->fetchAllAssociative('SELECT * FROM ' . RegistrationThrottle::table());
        try {
            RegistrationThrottle::reserve('another', 'another');
            self::fail('The default IP limit must reject attempt 21.');
        } catch (QUI\FrontendUsers\Exception $Exception) {
            self::assertSame($this->throttleMessage(), $Exception->getMessage());
        }
        self::assertSame($before, self::getConnection()->fetchAllAssociative('SELECT * FROM ' . RegistrationThrottle::table()));
        foreach ($before as $row) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $row['subject_key']);
            self::assertLessThanOrEqual(time() + 900, (int)$row['expires_at']);
            self::assertGreaterThan(time() + 890, (int)$row['expires_at']);
        }
        self::getConnection()->createQueryBuilder()->update(RegistrationThrottle::table())
            ->set('expires_at', ':expired')->setParameter('expired', time() - 1)->executeStatement();
        RegistrationThrottle::reserve('fresh', 'fresh');
        self::assertCount(3, self::getConnection()->fetchAllAssociative('SELECT * FROM ' . RegistrationThrottle::table()));
    }

    public function testFurtherAttemptsDoNotExtendAnExistingWindow(): void
    {
        $this->setPackageConfig('registration', 'throttleIpLimit', 2);
        RegistrationThrottle::reserve('first', 'first');
        $key = hash('sha256', 'ip:' . bin2hex(inet_pton('192.0.2.1')));
        $expiry = time() + 30;
        self::getConnection()->update(RegistrationThrottle::table(), ['expires_at' => $expiry], ['subject_key' => $key]);
        RegistrationThrottle::reserve('second', 'second');
        $row = self::getConnection()->fetchAssociative(
            'SELECT * FROM ' . RegistrationThrottle::table() . ' WHERE subject_key = :key',
            ['key' => $key]
        );
        self::assertSame($expiry, (int)$row['expires_at']);
        self::assertSame(2, (int)$row['attempts']);
        try {
            RegistrationThrottle::reserve('third', 'third');
            self::fail('The third attempt must be rejected.');
        } catch (QUI\FrontendUsers\Exception) {
            self::assertSame($expiry, (int)self::getConnection()->fetchOne(
                'SELECT expires_at FROM ' . RegistrationThrottle::table() . ' WHERE subject_key = :key',
                ['key' => $key]
            ));
        }
    }

    public function testDefaultIdentityLimitAndInvalidSettingsUseSafeDefaults(): void
    {
        $this->setPackageConfig('registration', 'throttleIpLimit', 0);
        $this->setPackageConfig('registration', 'throttleIdentityLimit', -1);
        for ($i = 0; $i < 5; $i++) {
            QUI::getRequest()->server->set('REMOTE_ADDR', '192.0.2.' . ($i + 1));
            RegistrationThrottle::reserve('same@example.invalid', 'different-' . $i);
        }
        $this->expectException(QUI\FrontendUsers\Exception::class);
        $this->expectExceptionMessage($this->throttleMessage());
        RegistrationThrottle::reserve('same@example.invalid', 'sixth');
    }

    public static function equivalentAddresses(): array
    {
        return [
            ['2001:db8::1', '2001:0db8:0:0:0:0:0:1'],
            ['192.0.2.1', '::ffff:192.0.2.1']
        ];
    }

    #[DataProvider('equivalentAddresses')]
    public function testEquivalentIpAddressesShareQuota(string $first, string $second): void
    {
        $this->setPackageConfig('registration', 'throttleIpLimit', 1);
        QUI::getRequest()->server->set('REMOTE_ADDR', $first);
        RegistrationThrottle::reserve('first', 'first');
        QUI::getRequest()->server->set('REMOTE_ADDR', $second);
        $this->expectException(QUI\FrontendUsers\Exception::class);
        RegistrationThrottle::reserve('second', 'second');
    }

    public function testUntrustedForwardedHeadersCannotBypassIpQuota(): void
    {
        $this->setPackageConfig('registration', 'throttleIpLimit', 1);
        $Request = QUI::getRequest();
        $proxies = $Request::getTrustedProxies();
        $headerSet = $Request::getTrustedHeaderSet();
        $headers = $Request->headers->all();
        try {
            $Request::setTrustedProxies([], $headerSet);
            RegistrationThrottle::reserve('first', 'first');
            $Request->headers->set('X-Forwarded-For', '198.51.100.2');
            $this->expectException(QUI\FrontendUsers\Exception::class);
            RegistrationThrottle::reserve('second', 'second');
        } finally {
            $Request::setTrustedProxies($proxies, $headerSet);
            $Request->headers->replace($headers);
        }
    }

    public function testMissingSourceRejectsWithoutSideEffects(): void
    {
        QUI::getRequest()->server->remove('REMOTE_ADDR');
        $before = $this->snapshot();
        self::assertFalse($this->attempt('rest', $this->registrationData()));
        self::assertSame($this->throttleMessage(), $this->message);
        self::assertSame($before, $this->snapshot());
    }

    public function testMailFailureRollsBackRegistrationButRetainsQuota(): void
    {
        $this->configureRegistrar(Handler::ACTIVATION_MODE_MAIL);
        $this->setPackageConfig('registration', 'throttleIdentityLimit', 1);
        $this->mailFailure = true;
        $data = $this->registrationData();
        $before = $this->snapshot();
        self::assertFalse($this->attempt('rest', $data));
        self::assertNotSame($this->throttleMessage(), $this->message);
        self::assertCount(1, $this->mails);
        $after = $this->snapshot();
        $after['mails'] = [];
        self::assertSame($before, $after);
        QUI::getRequest()->server->set('REMOTE_ADDR', '192.0.2.2');
        self::assertFalse($this->attempt('browser', $data));
        self::assertSame($this->throttleMessage(), $this->message);
        self::assertCount(1, $this->mails);
    }

    public function testUnavailableQuotaStoragePreventsRegistration(): void
    {
        $before = $this->snapshot();
        $Connection = self::getConnection();
        $Unavailable = $this->getMockBuilder(\Doctrine\DBAL\Connection::class)
            ->disableOriginalConstructor()->onlyMethods(['createQueryBuilder'])->getMock();
        $Unavailable->method('createQueryBuilder')->willThrowException(new \RuntimeException('Quota storage unavailable'));
        $Property = new ReflectionProperty(QUI::class, 'QueryBuilder');
        $Property->setValue(null, $Unavailable);
        try {
            self::assertFalse($this->attempt('rest', $this->registrationData()));
        } finally {
            $Property->setValue(null, $Connection);
        }
        self::assertSame($before, $this->snapshot());
    }

    private function throttleMessage(): string
    {
        return QUI::getLocale()->get('quiqqer/frontend-users', 'exception.registration.throttled');
    }

    private function snapshot(): array
    {
        $result = ['mails' => $this->mails];
        foreach (['users', 'users_address', 'groups', 'quiqqer_verification_processes'] as $table) {
            $result[$table] = self::getConnection()->fetchAllAssociative(
                'SELECT * FROM ' . QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName($table))
            );
        }
        return $result;
    }

    private function attempt(string $transport, array $data): bool
    {
        $this->message = '';
        if ($transport === 'rest') {
            $Response = PostRegister::call(
                (new ServerRequest('POST', '/frontend-users/register'))->withParsedBody($data),
                new Response(),
                []
            );
            $this->message = json_decode((string)$Response->getBody(), true)['message'];
            return $Response->getStatusCode() === 200;
        }
        $_POST = $data + ['registration' => 1];
        if ($transport === 'ajax') {
            require dirname(__DIR__, 2) . '/ajax/frontend/register.php';
            $callable = QUI::getAjax()->getRegisteredCallables()[
                'package_quiqqer_frontend-users_ajax_frontend_register'
            ]['callable'];
            $_REQUEST['registrar'] = (new Registrar())->getHash();
            $response = $callable((new Registrar())->getHash(), json_encode($data), '[]', false);
            $this->message = $response['html'];
            return $response['userId'] !== false;
        }
        try {
            (new Registration(['Registrar' => new Registrar()]))->register();
            return true;
        } catch (QUI\FrontendUsers\Exception $Exception) {
            $this->message = $Exception->getMessage();
            return false;
        }
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
