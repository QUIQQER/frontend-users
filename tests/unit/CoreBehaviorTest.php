<?php

namespace QUI\FrontendUsers\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\ActivationLinkVerification;
use QUI\FrontendUsers\Controls\Address\Address;
use QUI\FrontendUsers\EmailConfirmLinkVerification;
use QUI\FrontendUsers\EmailVerification;
use QUI\FrontendUsers\ErpProvider;
use QUI\FrontendUsers\Exception\EmailAddressNotVerifiableException;
use QUI\FrontendUsers\Exception\UserAlreadyExistsException;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\InvalidFormField;
use QUI\FrontendUsers\RegistrarCollection;
use QUI\FrontendUsers\RegistrationUtils;
use QUI\FrontendUsers\Rest\Provider;
use QUI\FrontendUsers\Utils;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;
use QUI\Verification\Enum\VerificationStatus;

class CoreBehaviorTest extends TestCase
{
    public function testFrontendUserExceptionCodes(): void
    {
        self::assertSame(50001, (new UserAlreadyExistsException())->getCode());
        self::assertSame(50002, (new EmailAddressNotVerifiableException())->getCode());
        self::assertSame(42, (new UserAlreadyExistsException('Custom code', 42))->getCode());
        self::assertSame(43, (new EmailAddressNotVerifiableException('Custom code', 43))->getCode());
    }

    public function testRequiredPackageResourcesAreAvailable(): void
    {
        self::assertInstanceOf(QUI\Config::class, Handler::getPackageConfig());

        $Control = new class extends QUI\Control {
            public function getTemplateFile(): string | false
            {
                return false;
            }
        };

        $this->expectException(QUI\Exception::class);
        Utils::getRequiredTemplateFile($Control);
    }

    public function testValueObjectsCollectionsAndSettingsNormalization(): void
    {
        $Field = new InvalidFormField('email', 'Invalid email');
        self::assertSame('email', $Field->getName());
        self::assertSame('Invalid email', $Field->getMsg());

        $Collection = new RegistrarCollection([]);
        self::assertCount(0, $Collection);

        $settings = Address::checkSettingsArray([
            'email' => ['show' => false, 'required' => false],
            'street_no' => ['show' => true, 'required' => true]
        ]);

        self::assertFalse($settings['email']['show']);
        self::assertTrue($settings['street']['show']);
        self::assertTrue($settings['street_number']['required']);
        self::assertTrue($settings['firstname']['required']);
    }

    public function testDefaultGroupIdsIgnoreEmptyEntries(): void
    {
        self::assertSame([], RegistrationUtils::parseDefaultGroupIds(''));
        self::assertSame(
            ['group-a', '42'],
            RegistrationUtils::parseDefaultGroupIds(' , group-a, ,42,')
        );
    }

    public function testGravatarAndEmailVerificationGuards(): void
    {
        $hash = md5('user@example.invalid');
        self::assertSame(
            'https://www.gravatar.com/avatar/' . $hash . '?s=1&d=mm',
            Utils::getGravatarUrl(' User@Example.Invalid ', 0)
        );
        self::assertStringContainsString('?s=2048&d=mm', Utils::getGravatarUrl('user@example.invalid', 4096));

        $User = $this->createMock(QUI\Interfaces\Users\User::class);
        $User->method('getAttribute')->willReturnMap([
            ['email', 'user@example.invalid'],
            [QUI\FrontendUsers\Handler::USER_ATTR_EMAIL_VERIFIED, true],
            [QUI\FrontendUsers\Handler::USER_ATTR_EMAIL_ADDRESSES_VERIFIED, []]
        ]);
        $User->method('getAddressList')->willReturn([]);

        self::assertTrue(Utils::isUserEmailVerified($User));
        self::assertTrue(Utils::isDefaultUserEmailVerified($User));
        self::assertTrue(Utils::doesUserHaveEmailAddress('user@example.invalid', $User));
        self::assertFalse(Utils::doesUserHaveEmailAddress('other@example.invalid', $User));
        self::assertFalse(Utils::isEmailAddressVerifiedForUser('other@example.invalid', $User));

        $this->expectException(EmailAddressNotVerifiableException::class);
        Utils::setEmailAddressAsVerifiedForUser('', $User);
    }

    public function testProvidersAndVerificationMessages(): void
    {
        $Provider = new Provider();
        self::assertSame('FrontendUsers', $Provider->getName());
        self::assertNotSame('', $Provider->getTitle(QUI::getLocale()));
        self::assertFileExists($Provider->getOpenApiDefinitionFile());
        self::assertIsArray(ErpProvider::getMailLocale());

        $Verification = $this->createVerification();
        $Activation = new ActivationLinkVerification();
        self::assertNotSame('', $Activation->getSuccessMessage($Verification));
        self::assertNotSame(
            $Activation->getErrorMessage($Verification, VerificationErrorReason::INVALID_REQUEST),
            $Activation->getErrorMessage($Verification, VerificationErrorReason::EXPIRED)
        );
        self::assertNull($Activation->getOnSuccessRedirectUrl($Verification));
        self::assertNull($Activation->getOnErrorRedirectUrl($Verification, VerificationErrorReason::INVALID_REQUEST));
        $Activation->onError($Verification, VerificationErrorReason::INVALID_REQUEST);

        $EmailConfirm = new EmailConfirmLinkVerification();
        self::assertSame('', $EmailConfirm->getSuccessMessage($Verification));
        self::assertSame('', $EmailConfirm->getErrorMessage($Verification, VerificationErrorReason::INVALID_REQUEST));
        self::assertNull($EmailConfirm->getOnSuccessRedirectUrl($Verification));
        $EmailConfirm->onError($Verification, VerificationErrorReason::INVALID_REQUEST);

        $Email = new EmailVerification();
        self::assertSame('', $Email->getSuccessMessage($Verification));
        self::assertSame('', $Email->getErrorMessage($Verification, VerificationErrorReason::INVALID_REQUEST));
        $Email->onError($Verification, VerificationErrorReason::INVALID_REQUEST);
    }

    private function createVerification(array $customData = []): LinkVerification
    {
        $now = new DateTimeImmutable();

        return new LinkVerification(
            'phpunit-verification',
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
