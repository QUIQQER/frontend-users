<?php

namespace QUI\FrontendUsers\Tests\Integration;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\Controls\RegistrationSignUp;
use QUI\FrontendUsers\Exception;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\RegistrationCaptcha;
use QUI\FrontendUsers\Registrars\Email\Control;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Rest\Routes\PostRegister;
use QUI\FrontendUsers\Tests\Fixtures\CaptchaDisplay;
use QUI\FrontendUsers\Tests\Fixtures\CaptchaHandler;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\Utils\Singleton;
use ReflectionProperty;

class RegistrationCaptchaWorkflowTest extends DatabaseTestCase
{
    private array $instances;
    private array $events;
    private array $autoloaders;
    private mixed $autoloadFilter = null;
    private QUI\Package\Manager $PackageManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->PackageManager = QUI::getPackageManager();
        $this->events = QUI::getEvents()->getList();
        $Property = new ReflectionProperty(Singleton::class, 'instances');
        $this->instances = $Property->getValue();
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendMail'])->getMock();
        $Handler->expects(self::never())->method('sendMail');
        $Property->setValue(null, array_replace($this->instances, [Handler::class => $Handler]));

        foreach ($this->events['onUserCreate'] ?? [] as $event) {
            if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                QUI::getEvents()->removeEvent('onUserCreate', $event['callable']);
            }
        }

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
            'addressInput' => 0, 'useCaptcha' => 1, 'termsOfUseRequired' => 0,
            'defaultGroups' => '', 'forcePasswordReset' => 0, 'sendInfoMailOnRegistrationTo' => '',
            'autoLoginOnActivation' => 0, 'userWelcomeMail' => 0, 'emailBlacklist' => '[]'
            ] as $key => $value
        ) {
            $this->setPackageConfig('registration', $key, $value);
        }

        $_POST = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        if ($this->autoloadFilter !== null) {
            spl_autoload_unregister($this->autoloadFilter);
            foreach ($this->autoloaders as $loader) {
                spl_autoload_register($loader);
            }
        }

        QUI::$PackageManager = $this->PackageManager;
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->instances);
        (new ReflectionProperty(QUI\Events\Event::class, 'events'))->setValue(QUI::getEvents(), $this->events);
        parent::tearDown();
    }

    public static function responses(): array
    {
        $cases = [];
        foreach (['package', 'handler', 'display', 'module', 'available'] as $capability) {
            foreach (['empty' => '', 'arbitrary' => 'arbitrary-nonempty', 'valid' => 'verified-test-challenge'] as $name => $response) {
                $cases[$capability . '-' . $name] = [$capability, $response];
            }
        }
        $cases['array-response'] = ['available', ['verified-test-challenge']];
        $cases['disabled-without-handler'] = ['disabled', 'arbitrary-nonempty'];
        return $cases;
    }

    #[DataProvider('responses')]
    public function testBrowserAndRestEnforceCaptchaWithoutSideEffects(string $capability, mixed $response): void
    {
        $this->configureCapability($capability);
        if ($capability === 'disabled') {
            $this->setPackageConfig('registration', 'useCaptcha', 0);
        }
        $success = $capability === 'disabled'
            || ($capability === 'available' && $response === 'verified-test-challenge');

        foreach (['browser', 'rest'] as $transport) {
            $username = self::TEST_PREFIX . 'captcha-' . bin2hex(random_bytes(4));
            $data = [
                'username' => $username, 'email' => $username . '@example.invalid',
                'password' => 'phpunit-captcha-password', 'captchaResponse' => $response
            ];
            $before = $this->counts();

            if ($transport === 'browser') {
                $_POST = $data + ['registration' => 1];
                try {
                    $status = (new Registration(['Registrar' => new Registrar()]))->register();
                    self::assertTrue($success, 'Registration must reject an unverified CAPTCHA.');
                    self::assertSame(Handler::REGISTRATION_STATUS_SUCCESS, $status);
                } catch (Exception $Exception) {
                    self::assertFalse($success, $Exception->getMessage());
                    if ($capability !== 'available') {
                        self::assertSame(
                            'exception.registrars.email.captcha_unavailable',
                            $Exception->getContext()['locale'][1]
                        );
                    }
                }
            } else {
                $Response = PostRegister::call(
                    (new ServerRequest('POST', '/frontend-users/register'))->withParsedBody($data),
                    new Response(),
                    []
                );
                self::assertSame($success ? 200 : 400, $Response->getStatusCode(), (string)$Response->getBody());
            }

            self::assertSame($success, QUI::getUsers()->usernameExists($username));
            if (!$success) {
                self::assertSame($before, $this->counts());
            }
        }

        self::assertSame(
            $capability === 'available' && is_string($response) && $response !== '' ? 2 : 0,
            CaptchaHandler::$validations
        );
    }

    public static function controls(): array
    {
        $cases = [];
        foreach ([Control::class, RegistrationSignUp::class] as $control) {
            foreach (['package', 'handler', 'display', 'module', 'available'] as $capability) {
                foreach ([false, true] as $enabled) {
                    $cases[] = [$control, $capability, $enabled];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('controls')]
    public function testRenderingHonorsConfiguredProtection(string $control, string $capability, bool $enabled): void
    {
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $this->configureCapability($capability);
        $this->setPackageConfig('registration', 'useCaptcha', (int)$enabled);
        if ($enabled && $capability !== 'available') {
            $this->expectException(Exception::class);
        }

        $body = (new $control())->getBody();
        if ($enabled) {
            self::assertStringContainsString('captcha-test-challenge', $body);
        } else {
            self::assertStringNotContainsString('captcha-test-challenge', $body);
        }
        self::assertSame(0, CaptchaHandler::$validations);
    }

    public static function settings(): array
    {
        return [
            'missing package' => ['package', 'quiqqer/frontend-users', 1, 1],
            'missing handler' => ['handler', 'quiqqer/frontend-users', 1, 1],
            'missing display' => ['display', 'quiqqer/frontend-users', 1, 1],
            'missing module' => ['module', 'quiqqer/frontend-users', 1, 1],
            'available' => ['available', 'quiqqer/frontend-users', 1, 0],
            'disabled' => ['handler', 'quiqqer/frontend-users', 0, 0],
            'other settings' => ['handler', 'quiqqer/frontend-users', null, 0],
            'other package' => ['handler', 'quiqqer/core', 1, 0]
        ];
    }

    #[DataProvider('settings')]
    public function testSettingsReportConfigurationErrorsWithoutDisablingProtection(
        string $capability,
        string $package,
        ?int $enabled,
        int $errors
    ): void {
        $this->configureCapability($capability);
        $this->setPackageConfig('registration', 'useCaptcha', $enabled ?? 1);
        $MessageHandler = $this->createMock(QUI\Messages\Handler::class);
        $MessageHandler->expects(self::exactly($errors))->method('addError');
        $Property = new ReflectionProperty(QUI::class, 'MessageHandler');
        $Previous = $Property->getValue();
        $Property->setValue(null, $MessageHandler);
        try {
            RegistrationCaptcha::onPackageConfigSave(
                QUI::getPackage($package),
                $enabled === null ? [] : ['registration' => ['useCaptcha' => $enabled]]
            );
            self::assertSame(
                $enabled ?? 1,
                (int)QUI::getPackage('quiqqer/frontend-users')->getConfig()->get('registration', 'useCaptcha')
            );
        } finally {
            $Property->setValue(null, $Previous);
        }
    }

    /** Replace optional components only inside this test's isolated PHP process. */
    private function configureCapability(string $capability): void
    {
        require_once dirname(__DIR__) . '/Fixtures/CaptchaHandler.php';
        require_once dirname(__DIR__) . '/Fixtures/CaptchaDisplay.php';
        CaptchaHandler::$available = $capability !== 'module';

        $blocked = match ($capability) {
            'handler', 'disabled' => 'QUI\\Captcha\\Handler',
            'display' => 'QUI\\Captcha\\Controls\\CaptchaDisplay',
            default => ''
        };
        foreach (
            [
            'QUI\\Captcha\\Handler' => CaptchaHandler::class,
            'QUI\\Captcha\\Controls\\CaptchaDisplay' => CaptchaDisplay::class
            ] as $target => $fixture
        ) {
            self::assertFalse(class_exists($target, false), 'Optional CAPTCHA class must not be preloaded.');
            if ($target !== $blocked) {
                class_alias($fixture, $target);
            }
        }

        $this->autoloaders = spl_autoload_functions();
        foreach ($this->autoloaders as $loader) {
            spl_autoload_unregister($loader);
        }
        $this->autoloadFilter = function (string $class) use ($blocked): void {
            if (strcasecmp($class, $blocked) === 0) {
                return;
            }
            foreach ($this->autoloaders as $loader) {
                $loader($class);
                if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
                    return;
                }
            }
        };
        spl_autoload_register($this->autoloadFilter);

        $Package = $this->createMock(QUI\Package\Package::class);
        $Manager = $this->getMockBuilder(QUI\Package\Manager::class)->onlyMethods(['getInstalledPackage'])->getMock();
        $Manager->method('getInstalledPackage')->willReturnCallback(function (string $name) use ($capability, $Package) {
            if ($name !== 'quiqqer/captcha') {
                return $this->PackageManager->getInstalledPackage($name);
            }
            if ($capability === 'package') {
                throw new QUI\Exception('Optional CAPTCHA package is absent.');
            }
            return $Package;
        });
        QUI::$PackageManager = $Manager;
    }

    private function counts(): array
    {
        $counts = [];
        foreach ([QUI\Users\Manager::table(), QUI::getDBTableName('quiqqer_verification_processes')] as $table) {
            $counts[] = self::getConnection()->createQueryBuilder()
                ->select('COUNT(*)')->from(QUI\Utils\Doctrine::quoteIdentifier($table))
                ->executeQuery()->fetchOne();
        }
        return $counts;
    }
}
