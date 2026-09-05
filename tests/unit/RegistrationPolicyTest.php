<?php

namespace QUI\FrontendUsers\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\Exception;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\RegistrarInterface;
use QUI\FrontendUsers\RegistrationPolicy;
use QUI\Utils\Singleton;
use ReflectionProperty;

class RegistrationPolicyTest extends TestCase
{
    private array $instances;
    private Handler $Handler;

    protected function setUp(): void
    {
        $Property = new ReflectionProperty(Singleton::class, 'instances');
        $this->instances = $Property->getValue();
        $this->Handler = $this->createMock(Handler::class);
        $Property->setValue(null, array_replace($this->instances, [Handler::class => $this->Handler]));
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->instances);
    }

    public function testDisabledRegistrarIsRejectedBeforeValidation(): void
    {
        $Registrar = $this->createMock(RegistrarInterface::class);
        $Registrar->method('isActive')->willReturn(false);
        $Registrar->expects(self::never())->method('validate');

        $this->expectException(Exception::class);
        (new RegistrationPolicy())->validate($Registrar);
    }

    public function testRequiredTermsAreRejectedBeforeRegistrarValidation(): void
    {
        $this->Handler->method('getRegistrationSettings')->willReturn(['termsOfUseRequired' => true]);
        $Registrar = $this->createMock(RegistrarInterface::class);
        $Registrar->method('isActive')->willReturn(true);
        $Registrar->expects(self::never())->method('validate');

        $this->expectException(Exception::class);
        (new RegistrationPolicy())->validate($Registrar);
    }

    public function testAcceptedTermsContinueThroughRegistrarValidation(): void
    {
        $this->Handler->method('getRegistrationSettings')->willReturn(['termsOfUseRequired' => true]);
        $Registrar = $this->createMock(RegistrarInterface::class);
        $Registrar->method('isActive')->willReturn(true);
        $Registrar->method('getAttribute')->with('termsOfUseAccepted')->willReturn(true);
        $Registrar->expects(self::once())->method('validate');

        (new RegistrationPolicy())->validate($Registrar);
    }

    public function testManualActivationDoesNotCallAnyActivationOrMailHook(): void
    {
        $this->Handler->method('getRegistrarSettings')->willReturn(['activationMode' => Handler::ACTIVATION_MODE_MANUAL]);
        $this->Handler->expects(self::never())->method('sendActivationMail');
        $this->Handler->expects(self::never())->method('sendEmailConfirmationMail');
        $User = $this->createMock(QUI\Interfaces\Users\User::class);
        $User->expects(self::never())->method('activate');

        self::assertSame(Handler::REGISTRATION_STATUS_SUCCESS, (new RegistrationPolicy())->activate(
            $User,
            $this->createMock(RegistrarInterface::class),
            $this->createMock(QUI\Projects\Project::class),
            static function (): bool {
                self::fail('Manual registration must not create an activation link.');
            }
        ));
    }

    public function testMailTransportFailureIsReported(): void
    {
        $this->Handler->method('getRegistrarSettings')->willReturn(['activationMode' => Handler::ACTIVATION_MODE_MAIL]);
        $this->Handler->expects(self::never())->method('sendActivationMail');
        $User = $this->createMock(QUI\Interfaces\Users\User::class);
        $User->expects(self::never())->method('activate');

        $this->expectException(Exception::class);
        (new RegistrationPolicy())->activate(
            $User,
            $this->createMock(RegistrarInterface::class),
            $this->createMock(QUI\Projects\Project::class),
            static fn(): bool => false
        );
    }
}
