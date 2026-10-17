<?php

namespace QUI\FrontendUsers\Tests\Integration;

use DateTimeImmutable;
use QUI;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationStatus;
use QUI\Verification\Interface\VerificationFactoryInterface;

class HandlerRegistrarWorkflowTest extends DatabaseTestCase
{
    public function testHandlerConfigurationAndRegistrarLookup(): void
    {
        $type = Registrar::class;
        $this->setRegistrarConfiguration($type, Handler::ACTIVATION_MODE_MANUAL);
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_REQUIRED);
        $this->setPackageConfig('registration', 'sendPassword', 0);
        $this->setPackageConfig('registration', 'userWelcomeMail', 0);

        $Handler = new Handler();
        self::assertIsArray($Handler->getUserProfileSettings());
        self::assertIsArray($Handler->getProfileBarSettings());
        self::assertIsArray($Handler->getRegistrationSettings());
        self::assertIsArray($Handler->getLoginSettings());
        self::assertIsArray($Handler->getAddressFieldSettings());
        self::assertIsArray($Handler->getMailSettings());
        self::assertSame(Handler::ACTIVATION_MODE_MANUAL, $Handler->getRegistrarSettings($type)['activationMode']);

        $Available = $Handler->getAvailableRegistrars();
        self::assertNotNull($Available);
        $Registrar = $Handler->getRegistrar($type);
        self::assertInstanceOf(Registrar::class, $Registrar);
        self::assertSame($Registrar, $Handler->getRegistrarByHash($Registrar->getHash()));
        self::assertFalse($Handler->getRegistrar('Not\\A\\Registrar'));
        self::assertFalse($Handler->getRegistrarByHash('not-a-hash'));
        self::assertNotEmpty($Handler->getRegistrars());

        $User = $this->createUser();
        self::assertFalse($Handler->getRegistrarByUser($User));
        $User->setAttribute(Handler::USER_ATTR_REGISTRAR, $type);
        self::assertInstanceOf(Registrar::class, $Handler->getRegistrarByUser($User));

        self::assertSame('registration-1', $Handler->createRegistrationId());
        self::assertSame('registration-2', $Handler->createRegistrationId());
        self::assertSame(50, $Handler->getUserAttributeLengthRestrictions()['username']);
        self::assertTrue($Handler->isUsernameInputAllowed());
        $Handler->checkConfiguration();

        $Project = QUI::getRewrite()->getProject();
        self::assertNotNull($Project);
        self::assertTrue($Handler->getRegistrationSite($Project) === false || $Handler->getRegistrationSite($Project) instanceof QUI\Projects\Site);
        self::assertTrue($Handler->getRegistrationSignUpSite($Project) === false || $Handler->getRegistrationSignUpSite($Project) instanceof QUI\Projects\Site);
        self::assertTrue($Handler->getLoginSite($Project) === false || $Handler->getLoginSite($Project) instanceof QUI\Projects\Site);
        self::assertTrue($Handler->getProfileSite($Project) === false || $Handler->getProfileSite($Project) instanceof QUI\Projects\Site);
        self::assertTrue($Handler->getRedirectOnActivationSite() === false || $Handler->getRedirectOnActivationSite() instanceof QUI\Projects\Site);
    }

    public function testEmailRegistrarValidatesAndPersistsRegistrationData(): void
    {
        $type = Registrar::class;
        $this->setRegistrarConfiguration($type, Handler::ACTIVATION_MODE_MANUAL);
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_REQUIRED);
        $this->setPackageConfig('registration', 'fullnameInput', Handler::FULLNAME_INPUT_FULLNAME_REQUIRED);
        $this->setPackageConfig('registration', 'addressInput', 1);
        $this->setPackageConfig('registration', 'useCaptcha', 0);
        $this->setPackageConfig('registration', 'passwordInput', Handler::PASSWORD_INPUT_NONE);
        $this->setPackageConfig('registration', 'emailBlacklist', json_encode([]));

        $suffix = bin2hex(random_bytes(6));
        $username = self::TEST_PREFIX . 'registrar-' . $suffix;
        $Registrar = new Registrar();
        $Registrar->setProject(QUI::getRewrite()->getProject());
        $Registrar->setAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Registration',
            'lastname' => 'Fixture',
            'salutation' => 'Mx',
            'company' => 'Example Ltd',
            'street_no' => 'Main Street 1',
            'zip' => '12345',
            'city' => 'Example City',
            'country' => 'DE',
            'phone' => '+49 30 123',
            'mobile' => '+49 170 123',
            'fax' => '+49 30 124',
            'password' => 'phpunit-registrar-password',
            'addressValidation' => false
        ]);

        self::assertSame([], $Registrar->validate());
        self::assertSame([], $Registrar->getInvalidFields());
        self::assertSame($username, $Registrar->getUsername());
        self::assertNotSame('', $Registrar->getTitle());
        self::assertNotSame('', $Registrar->getDescription());
        self::assertSame('fa fa-envelope', $Registrar->getIcon());
        self::assertTrue($Registrar->canSendPassword());
        self::assertTrue($Registrar->isActive());
        self::assertSame(hash('sha256', $Registrar->getType()), $Registrar->getHash());
        self::assertSame(QUI::getRewrite()->getProject(), $Registrar->getProject());
        self::assertInstanceOf(QUI\Control::class, $Registrar->getControl());
        $Registrar->checkUserAttributes();
        self::assertTrue((bool)Handler::getInstance()->getRegistrationSettings()['addressInput']);
        self::assertSame('Example City', $Registrar->getAttribute('city'));

        $User = $Registrar->createUser();
        self::assertNotNull($User->getStandardAddress());
        $UserAddress = $User->getStandardAddress();
        $Registrar->onRegistered($User);
        self::assertSame('Example City', $UserAddress->getAttribute('city'));
        $Reloaded = QUI::getUsers()->get($User->getUUID());
        self::assertSame($username . '@example.invalid', $Reloaded->getAttribute('email'));
        self::assertSame('Registration', $Reloaded->getAttribute('firstname'));
        self::assertSame([$username . '@example.invalid'], $UserAddress->getMailList());

        $Registrar->setAttribute('firstname', str_repeat('x', 41));
        $this->expectException(QUI\Exception::class);
        $Registrar->checkUserAttributes();
    }

    public function testMailWorkflowsUseVerificationFactoryAndProjectContextWithoutSending(): void
    {
        $this->setPackageConfig('registration', 'sendInfoMailOnRegistrationTo', 'admin@example.invalid');
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_REQUIRED);
        $Project = QUI::getRewrite()->getProject();
        self::assertNotNull($Project);
        $User = $this->createUser();
        $User->setAttribute(Handler::USER_ATTR_REGISTRAR, Registrar::class);
        $User->save(QUI::getUsers()->getSystemUser());
        $Verification = $this->createVerification();
        $Factory = $this->createMock(VerificationFactoryInterface::class);
        $Factory->method('createLinkVerification')->willReturn($Verification);

        $Handler = new class ($Factory) extends Handler {
            public array $mails = [];

            public function sendMail(
                array $mailData,
                array $recipients,
                string $templateFile,
                array $templateVars = [],
                ?QUI\Projects\Project $Project = null
            ): void {
                $this->mails[] = [$mailData, $recipients, $templateFile, $templateVars, $Project];
            }
        };

        $Registrar = new Registrar();
        $Registrar->setProject($Project);
        self::assertTrue($Handler->sendActivationMail($User, $Registrar));
        $Handler->sendWelcomeMail($User, $Project, 'generated-password');
        $Handler->sendRegistrationNotice($User, $Project);
        $Handler->sendChangeEmailAddressMail($User, 'changed@example.invalid', $Project);
        $Handler->sendEmailConfirmationMail($User, $User->getAttribute('email'), $Project);
        $Handler->sendDeleteUserConfirmationMail($User, $Project);
        self::assertCount(7, $Handler->mails);
        self::assertSame([$User->getAttribute('email')], $Handler->mails[4][1]);
        self::assertNull($Handler->mails[0][4]);
        self::assertSame($Project, $Handler->mails[1][4]);

        $RealHandler = new Handler($Factory);
        $RealHandler->sendMail([], [], __FILE__);
        $RealHandler->sendMail([], ['', false], __FILE__);
    }

    public function testEmailRegistrarReportsAllInvalidFormFields(): void
    {
        $type = Registrar::class;
        $this->setRegistrarConfiguration($type, Handler::ACTIVATION_MODE_MANUAL);
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_REQUIRED);
        $this->setPackageConfig('registration', 'fullnameInput', Handler::FULLNAME_INPUT_FULLNAME_REQUIRED);
        $this->setPackageConfig('registration', 'addressInput', 1);
        $this->setPackageConfig('registration', 'addressFields', json_encode([
            'firstname' => ['show' => true, 'required' => true],
            'lastname' => ['show' => true, 'required' => true],
            'street_no' => ['show' => true, 'required' => true],
            'zip' => ['show' => true, 'required' => true],
            'city' => ['show' => true, 'required' => true],
            'country' => ['show' => true, 'required' => true]
        ]));

        $Registrar = new Registrar();
        $Registrar->setAttributes([
            'username' => '',
            'email' => ''
        ]);
        $invalid = $Registrar->getInvalidFields();
        self::assertArrayHasKey('username', $invalid);
        self::assertArrayHasKey('email', $invalid);
        self::assertArrayHasKey('street_no', $invalid);
        self::assertSame('username', $invalid['username']->getName());
        self::assertNotSame('', $invalid['username']->getMsg());

        $_POST = [
            'registration' => 1,
            'registrar' => $Registrar->getHash()
        ];
        self::assertInstanceOf(QUI\FrontendUsers\Registrars\Email\Control::class, $Registrar->getControl());

        try {
            $Registrar->validate();
            self::fail('The invalid registrar data must be rejected.');
        } catch (QUI\FrontendUsers\Exception $Exception) {
            self::assertNotSame('', $Exception->getMessage());
        }

        $Existing = $this->createUser();
        $Registrar->setAttributes([
            'username' => $Existing->getUsername(),
            'email' => $Existing->getAttribute('email'),
            'firstname' => 'Existing',
            'lastname' => 'User'
        ]);
        $invalid = $Registrar->getInvalidFields();
        self::assertArrayHasKey('username', $invalid);
        self::assertArrayHasKey('email', $invalid);
    }

    private function setRegistrarConfiguration(string $type, string $activationMode): void
    {
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode($type) => [
                'active' => true,
                'activationMode' => $activationMode,
                'displayPosition' => 1
            ]
        ]));
    }

    private function createVerification(): LinkVerification
    {
        $now = new DateTimeImmutable();

        return new LinkVerification(
            'phpunit-mail-verification',
            'phpunit-mail-identifier',
            'phpunit-code',
            $now,
            $now,
            0,
            'https://example.invalid/verify',
            VerificationStatus::PENDING
        );
    }
}
