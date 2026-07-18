<?php

namespace QUI\FrontendUsers\Tests\Integration;

use QUI;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\Controls\RegistrationSignUp;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Control as EmailControl;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\RegistrationUtils;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use ReflectionMethod;

class RegistrationWorkflowTest extends DatabaseTestCase
{
    public function testManualEmailRegistrationCreatesPendingUserWithRandomPassword(): void
    {
        $Group = $this->createGroup();
        $this->configureRegistration($Group->getUUID());
        $suffix = bin2hex(random_bytes(6));
        $username = self::TEST_PREFIX . 'registration-' . $suffix;
        $Registrar = new Registrar();
        $Control = new Registration([
            'Registrar' => $Registrar,
            'addressValidation' => false
        ]);
        $_POST = [
            'registration' => 1,
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Registration',
            'lastname' => 'Workflow'
        ];
        $_REQUEST = $_POST;

        self::assertSame(Handler::REGISTRATION_STATUS_SUCCESS, $Control->register());
        $User = $Control->getRegisteredUser();
        self::assertNotNull($User);
        $this->trackUser($User);
        self::assertSame($username, $User->getUsername());
        self::assertTrue($User->isInGroup($Group->getUUID()));
        self::assertSame(Registrar::class, $User->getAttribute(Handler::USER_ATTR_REGISTRAR));
        self::assertTrue((bool)$User->getAttribute(Handler::USER_ATTR_USER_ACTIVATION_REQUIRED));

        $password = self::getConnection()->createQueryBuilder()
            ->select('password')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()))
            ->where('uuid = :uuid')
            ->setParameter('uuid', $User->getUUID())
            ->executeQuery()
            ->fetchOne();
        self::assertNotSame('', $password);

        $_POST = [];
        $_REQUEST = ['registrar' => $Registrar->getHash()];
        $Control->setAttribute('status', Handler::REGISTRATION_STATUS_SUCCESS);
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        self::assertNotSame('', $Control->getBody());
        $Control->setAttribute('status', Handler::REGISTRATION_STATUS_ERROR);
        self::assertNotSame('', $Control->getBody());
        $Control->setAttribute('Registrar', false);
        $Control->setAttribute('status', 'error');
        self::assertNotSame('', $Control->getBody());
        self::assertNotSame('', $Registrar->getSuccessMessage());
        self::assertNotSame('', $Registrar->getPendingMessage());
        self::assertNotSame('', $Registrar->getErrorMessage());
        self::assertNotSame('', RegistrationUtils::getFurtherLinksText());
    }

    public function testRegistrationRejectsMissingRegistrarAndAcceptedTermsAreRequired(): void
    {
        $Group = $this->createGroup();
        $this->configureRegistration($Group->getUUID());
        $_POST = ['registration' => 1];
        $Control = new Registration();

        try {
            $Control->register();
            self::fail('A registration without registrar must fail.');
        } catch (QUI\FrontendUsers\Exception $Exception) {
            self::assertNotSame('', $Exception->getMessage());
        }

        $this->setPackageConfig('registration', 'termsOfUseRequired', 1);
        $Registrar = new Registrar();
        $Control = new Registration(['Registrar' => $Registrar]);
        $_POST = [
            'registration' => 1,
            'username' => self::TEST_PREFIX . 'terms-' . bin2hex(random_bytes(4)),
            'email' => self::TEST_PREFIX . 'terms-' . bin2hex(random_bytes(4)) . '@example.invalid'
        ];
        $this->expectException(QUI\FrontendUsers\Exception::class);
        $Control->register();
    }

    public function testRegistrarResolutionAndFiltering(): void
    {
        $Group = $this->createGroup();
        $this->configureRegistration($Group->getUUID());
        $Control = new Registration(['registrars' => [Registrar::class]]);
        $getRegistrars = new ReflectionMethod($Control, 'getRegistrars');
        self::assertCount(1, $getRegistrars->invoke($Control));

        $_REQUEST = ['registrar' => 'Missing\\Registrar'];
        $isCurrentlyExecuted = new ReflectionMethod($Control, 'isCurrentlyExecuted');
        self::assertFalse($isCurrentlyExecuted->invoke($Control));
        $_REQUEST = ['registrar' => Registrar::class];
        self::assertInstanceOf(Registrar::class, $isCurrentlyExecuted->invoke($Control));
    }

    public function testRenderedRegistrationHandlesMissingRegistrarError(): void
    {
        $Group = $this->createGroup();
        $this->configureRegistration($Group->getUUID());
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $_POST = ['registration' => 1];
        $_REQUEST = $_POST;
        self::assertNotSame('', (new Registration(['async' => true]))->getBody());
    }

    public function testRenderedRegistrationHandlesUnexpectedRegistrarError(): void
    {
        $Group = $this->createGroup();
        $this->configureRegistration($Group->getUUID());
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $Registrar = new class extends Registrar {
            public function isActive(): bool
            {
                return true;
            }

            public function validate(): array
            {
                throw new \RuntimeException('phpunit registrar failure');
            }
        };
        $_POST = ['registration' => 1];
        $_REQUEST = $_POST;
        self::assertNotSame('', (new Registration([
            'async' => true,
            'Registrar' => $Registrar
        ]))->getBody());
    }

    public function testRenderedRegistrationHandlesAlreadyExistingUser(): void
    {
        $Group = $this->createGroup();
        $this->configureRegistration($Group->getUUID());
        $Existing = $this->createUser(true);
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $Registrar = new class ($Existing->getUsername()) extends Registrar {
            public function __construct(private readonly string $existingUsername)
            {
            }

            public function isActive(): bool
            {
                return true;
            }

            public function validate(): array
            {
                return [];
            }

            public function getUsername(): string
            {
                return $this->existingUsername;
            }
        };
        $_POST = ['registration' => 1];
        $_REQUEST = $_POST;
        self::assertNotSame('', (new Registration([
            'async' => true,
            'Registrar' => $Registrar
        ]))->getBody());
    }

    public function testRegistrationSignUpRendersSuccessAndErrorStatesAndIcons(): void
    {
        $Group = $this->createGroup();
        $this->configureRegistration($Group->getUUID());
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $Control = new RegistrationSignUp([
            'registrars' => [Registrar::class],
            'layout' => 'basic'
        ]);

        foreach (
            [
            ['success' => 'activation', 'registrar' => hash('sha256', Registrar::class)],
            ['success' => 'emailconfirm'],
            ['success' => 'userdelete'],
            ['error' => 'activation', 'email' => 'activation@example.invalid'],
            ['error' => 'activation_expired', 'email' => 'expired@example.invalid'],
            ['error' => 'already_verified'],
            ['error' => 'emailconfirm'],
            ['error' => 'userdelete'],
            ['error' => 'registration'],
            ['error' => 'login'],
            ['submitregistrar' => hash('sha256', Registrar::class)]
            ] as $query
        ) {
            $_GET = $query;
            $_REQUEST = $query;
            self::assertNotSame('', $Control->getBody());
        }

        self::assertStringContainsString('fa-envelope', $Control->getRegistrarIcon(new Registrar()));
        self::assertStringContainsString('background-image', $Control->getRegistrarIcon(
            new class {
                public function getIcon(): string
                {
                    return '/images/registrar.svg';
                }
            }
        ));

        $this->setPackageConfig('registration', 'addressInput', 1);
        $this->setPackageConfig('registration', 'addressFields', json_encode([
            'country' => ['show' => true, 'required' => true],
            'firstname' => ['show' => true, 'required' => true]
        ]));
        $EmailControl = new EmailControl([
            'fields' => ['country' => 'DE'],
            'invalidFields' => ['firstname']
        ]);
        self::assertNotSame('', $EmailControl->getBody());
        self::assertNotSame('', $EmailControl->renderAddress());
    }

    private function configureRegistration(string $groupUuid): void
    {
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode(Registrar::class) => [
                'active' => true,
                'activationMode' => Handler::ACTIVATION_MODE_MANUAL,
                'displayPosition' => 1
            ]
        ]));
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_REQUIRED);
        $this->setPackageConfig('registration', 'passwordInput', Handler::PASSWORD_INPUT_NONE);
        $this->setPackageConfig('registration', 'fullnameInput', Handler::FULLNAME_INPUT_FULLNAME_OPTIONAL);
        $this->setPackageConfig('registration', 'addressInput', 0);
        $this->setPackageConfig('registration', 'useCaptcha', 0);
        $this->setPackageConfig('registration', 'termsOfUseRequired', 0);
        $this->setPackageConfig('registration', 'defaultGroups', $groupUuid);
        $this->setPackageConfig('registration', 'forcePasswordReset', 0);
        $this->setPackageConfig('registration', 'sendInfoMailOnRegistrationTo', '');
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 0);
        $this->setPackageConfig('registration', 'reloadOnSuccess', 0);
        $this->setPackageConfig('registration', 'autoRedirectOnSuccess', json_encode([]));
        $this->setPackageConfig('registration', 'emailBlacklist', json_encode([]));
        $this->setPackageConfig('registration', 'visitRegistrationSiteBehaviour', 'showMessage');
    }
}
