<?php

namespace QUI\FrontendUsers\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\ActivationLinkVerification;
use QUI\FrontendUsers\Handler;
use QUI\Utils\Singleton;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;
use ReflectionProperty;

class ActivationRedirectTest extends TestCase
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

    public static function errorCases(): array
    {
        $cases = [];

        foreach (VerificationErrorReason::cases() as $reason) {
            foreach (['signup', 'registration'] as $site) {
                $cases[$reason->value . '-' . $site] = [$reason, $site];
            }
        }

        return $cases;
    }

    #[DataProvider('errorCases')]
    public function testActivationErrorRedirectDoesNotReadOrExposeAccountEmail(
        VerificationErrorReason $reason,
        string $site
    ): void {
        $email = 'activation+private@example.invalid';
        $Project = $this->createMock(QUI\Projects\Project::class);
        $Site = $this->createMock(QUI\Projects\Site::class);
        $this->Handler->method('getRegistrationSignUpSite')->with($Project)
            ->willReturn($site === 'signup' ? $Site : false);
        $this->Handler->expects($site === 'signup' ? self::never() : self::once())
            ->method('getRegistrationSite')->with($Project)->willReturn($Site);
        $expectedError = match ($reason) {
            VerificationErrorReason::ALREADY_VERIFIED => 'already_verified',
            VerificationErrorReason::EXPIRED => 'activation_expired',
            default => 'activation'
        };
        $Site->expects(self::once())->method('getUrlRewritten')
            ->with(['error'], ['error' => $expectedError, 'registrar' => 'test-registrar'])
            ->willReturnCallback(static fn(array $keys, array $params): string =>
                '/registration?' . http_build_query($params));

        $Activation = $this->getMockBuilder(ActivationLinkVerification::class)
            ->onlyMethods(['getProject', 'getUserEmail'])->getMock();
        $Activation->method('getProject')->willReturn($Project);
        $Activation->expects(self::never())->method('getUserEmail')->willReturn($email);
        $Verification = new LinkVerification(
            'verification-uuid',
            'activate-user-uuid',
            'secret-code',
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            0,
            '/verify',
            customData: ['uuid' => 'user-uuid', 'registrar' => 'test-registrar']
        );

        $url = $Activation->getOnErrorRedirectUrl($Verification, $reason);
        self::assertNotNull($url);
        self::assertStringContainsString('error=' . $expectedError, $url);
        self::assertStringNotContainsString('email=', $url);
        self::assertStringNotContainsString($email, $url);
        self::assertStringNotContainsString(rawurlencode($email), $url);
        self::assertStringNotContainsString(urlencode($email), $url);
    }
}
