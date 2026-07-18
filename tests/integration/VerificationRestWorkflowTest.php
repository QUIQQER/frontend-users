<?php

namespace QUI\FrontendUsers\Tests\Integration;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use QUI;
use QUI\FrontendUsers\ActivationLinkVerification;
use QUI\FrontendUsers\EmailConfirmLinkVerification;
use QUI\FrontendUsers\EmailVerification;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Rest\RegistrationData;
use QUI\FrontendUsers\Rest\Routes\GetRegisterRequiredFields;
use QUI\FrontendUsers\Rest\Routes\PostRegister;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\UserDeleteConfirmLinkVerification;
use QUI\FrontendUsers\Utils;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;
use QUI\Verification\Enum\VerificationStatus;
use ReflectionMethod;

class VerificationRestWorkflowTest extends DatabaseTestCase
{
    public function testEmailVerificationMarksPrimaryAndAddressMailAsVerified(): void
    {
        $User = $this->createUser();
        $primaryEmail = (string)$User->getAttribute('email');

        $Handler = new EmailVerification();
        $Handler->onSuccess($this->createVerification([
            'uuid' => $User->getUUID(),
            'email' => $primaryEmail
        ]));
        self::assertTrue((bool)Utils::isDefaultUserEmailVerified($User));
        self::assertTrue(Utils::isUserEmailVerified($User));
        self::assertTrue(Utils::isEmailAddressVerifiedForUser($primaryEmail, $User));

        self::assertFalse(Utils::doesUserHaveEmailAddress('absent@example.invalid', $User));
        self::assertSame('', $Handler->getSuccessMessage($this->createVerification()));
        self::assertSame('', $Handler->getErrorMessage(
            $this->createVerification(),
            VerificationErrorReason::INVALID_REQUEST
        ));
        $Handler->onError($this->createVerification(), VerificationErrorReason::EXPIRED);
        self::assertGreaterThan(0, $Handler->getValidDuration($this->createVerification()));
    }

    public function testEmailChangeAndActivationVerificationUpdateUser(): void
    {
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_NONE);
        $this->setPackageConfig('registration', 'userWelcomeMail', 0);
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 0);
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode(Registrar::class) => [
                'active' => true,
                'activationMode' => Handler::ACTIVATION_MODE_MANUAL,
                'displayPosition' => 1
            ]
        ]));

        $oldEmail = self::TEST_PREFIX . bin2hex(random_bytes(4)) . '@example.invalid';
        $newEmail = self::TEST_PREFIX . bin2hex(random_bytes(4)) . '@example.invalid';
        $User = $this->createUser(false, [
            'username' => $oldEmail,
            'email' => $oldEmail
        ]);
        $User->setAttribute(Handler::USER_ATTR_REGISTRAR, Registrar::class);
        $User->setPassword('phpunit-verification-password', QUI::getUsers()->getSystemUser());
        $User->save(QUI::getUsers()->getSystemUser());

        $EmailConfirm = new EmailConfirmLinkVerification();
        $verification = $this->createVerification([
            'uuid' => $User->getUUID(),
            'newEmail' => $newEmail
        ]);
        $EmailConfirm->onSuccess($verification);
        self::assertSame($newEmail, $User->getAttribute('email'));
        self::assertSame($newEmail, $User->getUsername());
        self::assertTrue(Utils::isEmailAddressVerifiedForUser($newEmail, $User));
        self::assertSame('', $EmailConfirm->getSuccessMessage($verification));
        self::assertSame('', $EmailConfirm->getErrorMessage($verification, VerificationErrorReason::EXPIRED));
        $EmailConfirm->onError($verification, VerificationErrorReason::INVALID_REQUEST);

        $Activation = new ActivationLinkVerification();
        $Activation->onSuccess($this->createVerification([
            'uuid' => $User->getUUID(),
            'registrar' => hash('sha256', Registrar::class)
        ]));
        self::assertTrue($User->isActive());
        self::assertTrue(Utils::isDefaultUserEmailVerified($User));
        self::assertNotSame('', $Activation->getSuccessMessage($verification));
        self::assertNotSame('', $Activation->getErrorMessage($verification, VerificationErrorReason::EXPIRED));
        self::assertNotSame('', $Activation->getErrorMessage($verification, VerificationErrorReason::INVALID_REQUEST));
        $Activation->onError($verification, VerificationErrorReason::EXPIRED);
        self::assertTrue(
            $Activation->getOnSuccessRedirectUrl($verification) === null
            || is_string($Activation->getOnSuccessRedirectUrl($verification))
        );
        foreach (
            [
            VerificationErrorReason::ALREADY_VERIFIED,
            VerificationErrorReason::EXPIRED,
            VerificationErrorReason::INVALID_REQUEST
            ] as $reason
        ) {
            self::assertTrue(
                $Activation->getOnErrorRedirectUrl($verification, $reason) === null
                || is_string($Activation->getOnErrorRedirectUrl($verification, $reason))
            );
        }
        self::assertTrue(
            $EmailConfirm->getOnSuccessRedirectUrl($verification) === null
            || is_string($EmailConfirm->getOnSuccessRedirectUrl($verification))
        );
        self::assertTrue(
            $EmailConfirm->getOnErrorRedirectUrl($verification, VerificationErrorReason::EXPIRED) === null
            || is_string($EmailConfirm->getOnErrorRedirectUrl($verification, VerificationErrorReason::EXPIRED))
        );
    }

    public function testDeleteConfirmationUsesConfiguredSoftDeleteMode(): void
    {
        $this->setPackageConfig('userProfile', 'userDeleteMode', 'delete');
        $User = $this->createUser(true);
        self::replaceSessionUser($User);
        $verification = $this->createVerification(['uuid' => $User->getUUID()]);
        $Handler = new UserDeleteConfirmLinkVerification();
        $Handler->onSuccess($verification);

        $active = self::getConnection()->createQueryBuilder()
            ->select('active')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()))
            ->where('uuid = :uuid')
            ->setParameter('uuid', $User->getUUID())
            ->executeQuery()
            ->fetchOne();
        self::assertSame(-1, (int)$active);
        self::assertNotSame('', $Handler->getSuccessMessage($verification));
        self::assertSame('', $Handler->getErrorMessage($verification, VerificationErrorReason::EXPIRED));
        self::assertGreaterThan(0, $Handler->getValidDuration($verification));
        $Handler->onError($verification, VerificationErrorReason::INVALID_REQUEST);
        self::assertTrue(
            $Handler->getOnSuccessRedirectUrl($verification) === null
            || is_string($Handler->getOnSuccessRedirectUrl($verification))
        );
        self::assertTrue(
            $Handler->getOnErrorRedirectUrl($verification, VerificationErrorReason::EXPIRED) === null
            || is_string($Handler->getOnErrorRedirectUrl($verification, VerificationErrorReason::EXPIRED))
        );
    }

    public function testRestRegistrationDataValidationAndAddressMapping(): void
    {
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_REQUIRED);
        $this->setPackageConfig('registration', 'passwordInput', Handler::PASSWORD_INPUT_DEFAULT);
        $this->setPackageConfig('registration', 'fullnameInput', Handler::FULLNAME_INPUT_FULLNAME_REQUIRED);
        $this->setPackageConfig('registration', 'addressInput', 1);
        $this->setPackageConfig('registration', 'addressFields', json_encode([
            'street_no' => ['show' => true, 'required' => true],
            'zip' => ['show' => true, 'required' => true],
            'city' => ['show' => true, 'required' => true],
            'country' => ['show' => true, 'required' => true]
        ]));
        $suffix = bin2hex(random_bytes(5));
        $data = [
            'username' => self::TEST_PREFIX . 'rest-' . $suffix,
            'password' => 'phpunit-rest-password',
            'email' => self::TEST_PREFIX . 'rest-' . $suffix . '@example.invalid',
            'firstname' => 'REST',
            'lastname' => 'Fixture',
            'street_no' => 'Main Street 1',
            'zip' => '12345',
            'city' => 'Example City',
            'country' => 'DE',
            'phone' => '+49 30 123',
            'mobile' => '+49 170 123',
            'fax' => '+49 30 124'
        ];
        $Request = (new ServerRequest('POST', '/frontend-users/register'))->withParsedBody($data);
        $RegistrationData = RegistrationData::buildFromRequest($Request);
        $RegistrationData->validate();
        self::assertSame($data['username'], $RegistrationData->getAttribute('username'));
        self::assertArrayHasKey('email', RegistrationData::getRequiredFields());
        self::assertArrayHasKey('street_no', RegistrationData::getRequiredFields());

        $User = $this->createUser();
        $Method = new ReflectionMethod(PostRegister::class, 'addRegistrationDataToUser');
        $Method->invoke(null, $User, $RegistrationData);
        self::assertSame('REST', $User->getAttribute('firstname'));
        self::assertSame('Example City', $User->getStandardAddress()->getAttribute('city'));

        $Response = GetRegisterRequiredFields::call(
            new ServerRequest('GET', '/frontend-users/register/required-fields'),
            new Response(),
            []
        );
        self::assertSame(200, $Response->getStatusCode());
        self::assertStringContainsString('email', (string)$Response->getBody());

        $EmailHandler = new EmailVerification();
        $verification = $this->createVerification();
        self::assertTrue(
            $EmailHandler->getOnSuccessRedirectUrl($verification) === null
            || is_string($EmailHandler->getOnSuccessRedirectUrl($verification))
        );
        self::assertTrue(
            $EmailHandler->getOnErrorRedirectUrl($verification, VerificationErrorReason::EXPIRED) === null
            || is_string($EmailHandler->getOnErrorRedirectUrl($verification, VerificationErrorReason::EXPIRED))
        );

        $Response = PostRegister::call(
            (new ServerRequest('POST', '/frontend-users/register'))->withParsedBody([]),
            new Response(),
            []
        );
        self::assertSame(400, $Response->getStatusCode());
    }

    public function testRestRegistrationCreatesUserWithoutDeliveringMailForEmptyRecipient(): void
    {
        $Group = $this->createGroup();
        $this->setPackageConfig('registration', 'defaultGroups', $Group->getUUID());
        $this->setPackageConfig('registration', 'forcePasswordReset', 1);
        $this->setPackageConfig('registration', 'addressInput', 0);
        $this->setPackageConfig('registration', 'sendInfoMailOnRegistrationTo', '');
        $suffix = bin2hex(random_bytes(5));
        $RegistrationData = new class extends RegistrationData {
            public function validate(): void
            {
            }
        };
        $RegistrationData->setAttributes([
            'username' => self::TEST_PREFIX . 'rest-create-' . $suffix,
            'email' => '',
            'firstname' => 'REST',
            'lastname' => 'Created',
            'password' => 'phpunit-rest-created-password'
        ]);
        $Method = new ReflectionMethod(PostRegister::class, 'registerUser');
        $User = $Method->invoke(null, $RegistrationData);
        $this->trackUser($User);

        self::assertSame('REST', $User->getAttribute('firstname'));
        self::assertTrue($User->isInGroup($Group->getUUID()));
        self::assertTrue((bool)$User->getAttribute('quiqqer.set.new.password'));
    }

    public function testRestRegistrationValidationRejectsEachRequiredInputClass(): void
    {
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_REQUIRED);
        $this->setPackageConfig('registration', 'passwordInput', Handler::PASSWORD_INPUT_DEFAULT);
        $this->setPackageConfig('registration', 'fullnameInput', Handler::FULLNAME_INPUT_FULLNAME_REQUIRED);
        $this->setPackageConfig('registration', 'addressInput', 0);
        $suffix = bin2hex(random_bytes(5));
        $valid = [
            'username' => self::TEST_PREFIX . 'validate-' . $suffix,
            'password' => 'phpunit-validation-password',
            'email' => self::TEST_PREFIX . 'validate-' . $suffix . '@example.invalid',
            'firstname' => 'Validation',
            'lastname' => 'Fixture'
        ];

        foreach (
            [
            ['email' => ''],
            ['email' => 'not-an-email'],
            ['username' => ''],
            ['username' => str_repeat('u', 51)],
            ['password' => ''],
            ['firstname' => ''],
            ['lastname' => ''],
            ['firstname' => str_repeat('f', 41)]
            ] as $changes
        ) {
            $Data = new RegistrationData();
            $Data->setAttributes(array_merge($valid, $changes));
            try {
                $Data->validate();
                self::fail('Invalid REST registration data must be rejected.');
            } catch (QUI\FrontendUsers\Exception $Exception) {
                self::assertNotSame('', $Exception->getMessage());
            }
        }

        $Existing = $this->createUser();
        $Data = new RegistrationData();
        $Data->setAttributes(array_merge($valid, ['email' => $Existing->getAttribute('email')]));
        try {
            $Data->validate();
            self::fail('An existing email address must be rejected.');
        } catch (QUI\FrontendUsers\Exception $Exception) {
            self::assertNotSame('', $Exception->getMessage());
        }

        $this->setPackageConfig('registration', 'addressInput', 1);
        $this->setPackageConfig('registration', 'addressFields', json_encode([
            'city' => ['show' => true, 'required' => true]
        ]));
        $Data = new RegistrationData();
        $Data->setAttributes($valid);
        try {
            $Data->validate();
            self::fail('Missing required address fields must be rejected.');
        } catch (QUI\FrontendUsers\Exception $Exception) {
            self::assertNotSame('', $Exception->getMessage());
        }
    }

    private function createVerification(array $customData = []): LinkVerification
    {
        $Project = QUI::getRewrite()->getProject();
        if ($Project) {
            $customData += [
                'project' => $Project->getName(),
                'projectLang' => $Project->getLang()
            ];
        }

        $now = new DateTimeImmutable();

        return new LinkVerification(
            'phpunit-verification-' . bin2hex(random_bytes(4)),
            'phpunit-identifier',
            'phpunit-code',
            $now,
            $now,
            0,
            'https://example.invalid/verify',
            VerificationStatus::PENDING,
            $customData
        );
    }
}
