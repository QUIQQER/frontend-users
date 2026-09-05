<?php

namespace QUI\FrontendUsers\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\RegistrationTransaction;
use QUI\FrontendUsers\RegistrationThrottle;
use QUI\FrontendUsers\Rest\RegistrationData;
use QUI\FrontendUsers\Rest\Routes\PostRegister;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Tests\Support\DatabaseEnvironment;
use QUI\FrontendUsers\Tests\Support\VerificationSiteFixture;
use QUI\Utils\Singleton;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

class RegistrationTransactionWorkflowTest extends DatabaseTestCase
{
    private array $events;
    private array $instances;
    private array $mails = [];
    private bool $failMail = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->events = QUI::getEvents()->getList();
        foreach ($this->events['onUserCreate'] ?? [] as $event) {
            if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                QUI::getEvents()->removeEvent('onUserCreate', $event['callable']);
            }
        }
        $instances = new ReflectionProperty(Singleton::class, 'instances');
        $this->instances = $instances->getValue();
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendMail'])->getMock();
        $Handler->method('sendMail')->willReturnCallback(function (array $data, array $recipients): void {
            if ($this->failMail) {
                throw new RuntimeException('Injected activation mail failure.');
            }
            $this->mails[] = $recipients;
        });
        $instances->setValue(null, array_replace($this->instances, [Handler::class => $Handler]));
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode(Registrar::class) => ['active' => true, 'activationMode' => 'mail', 'displayPosition' => 1]
        ]));
        foreach (
            [
            'usernameInput' => Handler::USERNAME_INPUT_REQUIRED,
            'passwordInput' => Handler::PASSWORD_INPUT_DEFAULT,
            'fullnameInput' => Handler::FULLNAME_INPUT_FULLNAME_OPTIONAL,
            'addressInput' => 0,
            'useCaptcha' => 0,
            'termsOfUseRequired' => 0,
            'defaultGroups' => '',
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

    public static function concurrentCases(): iterable
    {
        foreach ([['browser', 'browser'], ['rest', 'rest'], ['browser', 'rest']] as $transports) {
            foreach (['email', 'username', 'distinct'] as $identity) {
                yield implode('-', $transports) . '-' . $identity => [$transports, $identity];
            }
        }
    }

    #[DataProvider('concurrentCases')]
    public function testConcurrentRegistrations(array $transports, string $identity): void
    {
        $dir = sys_get_temp_dir() . '/frontend-users-race-' . bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $processes = [];
        $Shared = null;
        $Group = null;
        $data = [];
        $committed = false;
        $ip = '2001:db8:' . implode(':', str_split(bin2hex(random_bytes(12)), 4));
        try {
            $Group = $this->createGroup();
            $this->setPackageConfig('registration', 'defaultGroups', $Group->getUUID());
            $database = $dir . '/database.sqlite';
            if (DatabaseEnvironment::usesCiDatabase()) {
                // Worker connections must see this test's group and verifier fixture.
                self::getConnection()->commit();
                $committed = true;
            } else {
                self::getConnection()->executeStatement('VACUUM INTO ' . self::getConnection()->quote($database));
            }
            $Shared = DatabaseEnvironment::createConnection($database);
            $before = $this->counts($Shared);
            $data = [$this->data(), $this->data()];
            if ($identity !== 'distinct') {
                $data[1][$identity] = $data[0][$identity];
            }
            foreach ($transports as $i => $transport) {
                $input = $dir . '/input-' . $i . '.json';
                file_put_contents($input, json_encode([
                    'database' => $database, 'transport' => $transport, 'data' => $data[$i], 'ip' => $ip,
                    'ready' => $dir . '/ready-' . $i, 'go' => $dir . '/go', 'result' => $dir . '/result-' . $i
                ], JSON_THROW_ON_ERROR));
                $pipes = [];
                $process = proc_open(
                    [
                    PHP_BINARY, __DIR__ . '/../../tools/phpunit', '--no-configuration',
                    '--bootstrap', __DIR__ . '/../phpunit-bootstrap.php',
                    __DIR__ . '/../Fixtures/RegistrationRaceWorkerTest.php'
                    ],
                    [1 => ['file', $dir . '/output-' . $i, 'w'], 2 => ['file', $dir . '/error-' . $i, 'w']],
                    $pipes,
                    null,
                    array_replace(getenv(), ['FRONTEND_USERS_RACE_INPUT' => $input])
                );
                self::assertIsResource($process);
                $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            while (!file_exists($dir . '/ready-0') || !file_exists($dir . '/ready-1')) {
                if (microtime(true) > $deadline) {
                    self::fail('Workers did not reach the barrier: ' . $this->workerOutput($dir));
                }
                usleep(10000);
            }
            file_put_contents($dir . '/go', 'go');
            foreach ($processes as $process) {
                self::assertSame(0, proc_close($process), $this->workerOutput($dir));
            }
            $processes = [];
            $results = [];
            foreach ([0, 1] as $i) {
                self::assertFileExists($dir . '/result-' . $i, $this->workerOutput($dir));
                $results[] = json_decode(file_get_contents($dir . '/result-' . $i), true, flags: JSON_THROW_ON_ERROR);
            }
            $winners = $identity === 'distinct' ? 2 : 1;
            self::assertCount($winners, array_filter($results, static fn(array $r): bool => $r['success']), json_encode($results));
            foreach ($results as $result) {
                if (!$result['success']) {
                    self::assertSame(QUI\FrontendUsers\Exception::class, $result['exception']);
                    self::assertSame([], $result['mails']);
                }
            }
            $after = $this->counts($Shared);
            self::assertSame($before['users'] + $winners, $after['users']);
            self::assertSame($before['users_address'] + $winners, $after['users_address']);
            self::assertSame($before['groups'], $after['groups']);
            self::assertSame($before['quiqqer_verification_processes'] + $winners, $after['quiqqer_verification_processes']);
            $users = $Shared->fetchAllAssociative('SELECT usergroup FROM ' . QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()) . ' WHERE username IN (?, ?)', [$data[0]['username'], $data[1]['username']]);
            self::assertCount($winners, $users);
            foreach ($users as $user) {
                self::assertStringContainsString(',' . $Group->getUUID() . ',', $user['usergroup']);
            }
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            $Shared?->close();
            if ($committed) {
                $this->removeCommittedFixtures($data, $ip);
                $Group?->delete();
            }
            foreach (glob($dir . '/*') as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    private function removeCommittedFixtures(array $data, string $ip): void
    {
        $Connection = self::getConnection();
        $keys = ['ip:' . bin2hex(inet_pton($ip))];
        foreach ($data as $values) {
            $uuids = $Connection->fetchFirstColumn(
                'SELECT uuid FROM ' . QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table())
                . ' WHERE username = ? AND email = ?',
                [$values['username'], $values['email']]
            );
            foreach ($uuids as $uuid) {
                $Connection->delete(QUI::getDBTableName('quiqqer_verification_processes'), [
                    'identifier' => 'activate-' . $uuid
                ]);
                QUI::getUsers()->get($uuid)->delete(QUI::getUsers()->getSystemUser());
            }
            $keys[] = 'username:' . mb_strtolower($values['username'], 'UTF-8');
            $keys[] = 'email:' . mb_strtolower($values['email'], 'UTF-8');
        }
        foreach ($keys as $key) {
            $Connection->delete(RegistrationThrottle::table(), ['subject_key' => hash('sha256', $key)]);
        }
    }

    public static function transports(): array
    {
        return [['browser'], ['rest']];
    }

    #[DataProvider('transports')]
    public function testFailureAfterUserAndAddressCreationRollsBackAndAllowsRetry(string $transport): void
    {
        $before = $this->counts(self::getConnection());
        $data = $this->data();
        $failure = static function (): void {
            throw new RuntimeException('registration transaction regression');
        };
        QUI::getEvents()->addEvent('onUserCreate', $failure);
        try {
            $this->register($transport, $data);
            self::fail('Expected the injected failure.');
        } catch (\Throwable $Exception) {
            self::assertStringContainsString('registration transaction regression', $Exception->getMessage());
        } finally {
            QUI::getEvents()->removeEvent('onUserCreate', $failure);
        }
        self::assertSame($before, $this->counts(self::getConnection()));
        self::assertSame([], $this->mails);
        $this->register($transport, $data);
        self::assertTrue(QUI::getUsers()->emailExists($data['email']));
    }

    public function testMissingLockFailsClosedAndSetupIsRepeatable(): void
    {
        $Connection = self::getConnection();
        $Connection->delete(RegistrationTransaction::table(), ['id' => 1]);
        $before = $this->counts($Connection);
        try {
            $this->register('rest', $this->data());
            self::fail('Missing lock must prevent registration.');
        } catch (QUI\Exception $Exception) {
            self::assertStringContainsString('registration lock is missing', $Exception->getMessage());
        }
        self::assertSame($before, $this->counts($Connection));
        RegistrationTransaction::setup();
        RegistrationTransaction::setup();
        self::assertSame(1, (int)$Connection->fetchOne('SELECT COUNT(*) FROM ' . RegistrationTransaction::table()));
    }

    #[DataProvider('transports')]
    public function testMailFailureRollsBackUserAddressGroupsAndVerification(string $transport): void
    {
        $Group = $this->createGroup();
        $this->setPackageConfig('registration', 'defaultGroups', $Group->getUUID());
        $this->setPackageConfig('registration', 'addressInput', 1);
        $before = $this->counts(self::getConnection());
        $data = $this->data();
        $this->failMail = true;
        try {
            $this->register($transport, $data);
            self::fail('The injected mail failure must abort registration.');
        } catch (QUI\FrontendUsers\Exception) {
            self::assertSame($before, $this->counts(self::getConnection()));
        } finally {
            $this->failMail = false;
        }
        self::assertSame([], $this->mails);
        $this->register($transport, $data);
        self::assertTrue(QUI::getUsers()->emailExists($data['email']));
        self::assertSame($before['quiqqer_verification_processes'] + 1, $this->counts(self::getConnection())['quiqqer_verification_processes']);
    }

    public function testRegistrarFailureIsNotSwallowed(): void
    {
        $Registrar = $this->getMockBuilder(Registrar::class)->onlyMethods(['onRegistered', 'getType'])->getMock();
        $Registrar->method('getType')->willReturn(Registrar::class);
        $Registrar->method('onRegistered')->willThrowException(new RuntimeException('registrar failure'));
        $_POST = $this->data() + ['registration' => 1];
        $before = $this->counts(self::getConnection());
        try {
            (new Registration(['Registrar' => $Registrar]))->register();
            self::fail('Registrar failure must abort registration.');
        } catch (RuntimeException $Exception) {
            self::assertSame('registrar failure', $Exception->getMessage());
        }
        self::assertSame($before, $this->counts(self::getConnection()));
        self::assertSame([], $this->mails);
    }

    public function testFailureDuringAutomaticLoginRestoresSession(): void
    {
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode(Registrar::class) => [
                'active' => true, 'activationMode' => Handler::ACTIVATION_MODE_AUTO_WITH_EMAIL_CONFIRM, 'displayPosition' => 1
            ]
        ]));
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 1);
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $Session = QUI::getSession()->getSymfonySession();
        self::assertNotFalse($Session);
        $sessionValues = $Session->all();
        $before = $this->counts(self::getConnection());
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendEmailConfirmationMail'])->getMock();
        $Handler->method('sendEmailConfirmationMail')->willReturnCallback(static function (): void {
            self::assertNotEmpty(QUI::getSession()->get('uid'));
            throw new RuntimeException('automatic login regression');
        });
        $instances = new ReflectionProperty(Singleton::class, 'instances');
        $instances->setValue(null, array_replace($instances->getValue(), [Handler::class => $Handler]));
        try {
            $this->register('browser', $this->data());
            self::fail('Expected automatic login hook to fail.');
        } catch (\Throwable $Exception) {
            self::assertStringContainsString('automatic login regression', $Exception->getMessage());
        }
        self::assertSame($before, $this->counts(self::getConnection()));
        self::assertSame($sessionValues, $Session->all());
    }

    private function register(string $transport, array $data): void
    {
        if ($transport === 'browser') {
            $_POST = $data + ['registration' => 1];
            (new Registration(['Registrar' => new Registrar()]))->register();
            return;
        }
        $Data = new RegistrationData();
        $Data->setAttributes($data);
        (new ReflectionMethod(PostRegister::class, 'registerUser'))->invoke(null, $Data);
    }

    private function data(): array
    {
        $name = self::TEST_PREFIX . bin2hex(random_bytes(4));
        return [
            'username' => $name, 'email' => $name . '@example.invalid', 'password' => 'phpunit-registration-password',
            'firstname' => 'Registration', 'lastname' => 'Regression', 'salutation' => 'mr',
            'street_no' => 'Test street 1', 'zip' => '12345', 'city' => 'Test city', 'country' => 'de'
        ];
    }

    private function counts(\Doctrine\DBAL\Connection $Connection): array
    {
        $counts = [];
        foreach (['users', 'users_address', 'groups', 'quiqqer_verification_processes'] as $table) {
            $counts[$table] = (int)$Connection->fetchOne('SELECT COUNT(*) FROM ' . QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName($table)));
        }
        return $counts;
    }

    private function workerOutput(string $dir): string
    {
        $output = '';
        foreach (['output-0', 'error-0', 'output-1', 'error-1'] as $name) {
            if (is_file($dir . '/' . $name)) {
                $output .= file_get_contents($dir . '/' . $name);
            }
        }
        return $output;
    }
}
