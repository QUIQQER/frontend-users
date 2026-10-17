<?php

namespace QUI\FrontendUsers\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\ProfileSecurity;
use QUI\Security\CsrfToken;

class ProfileSecurityTest extends TestCase
{
    private array $sessionValues = [];
    private array $requestValues;
    private string $requestMethod;

    protected function setUp(): void
    {
        $this->requestValues = QUI::getRequest()->request->all();
        $this->requestMethod = QUI::getRequest()->getMethod();

        foreach (
            [
            CsrfToken::SESSION_KEY, ProfileSecurity::RECENT_AUTH_SESSION_KEY,
            'uid', 'auth', 'auth-primary', 'auth-secondary'
            ] as $key
        ) {
            $this->sessionValues[$key] = QUI::getSession()->get($key);
            QUI::getSession()->remove($key);
        }
    }

    protected function tearDown(): void
    {
        QUI::getRequest()->request->replace($this->requestValues);
        QUI::getRequest()->setMethod($this->requestMethod);

        foreach ($this->sessionValues as $key => $value) {
            QUI::getSession()->set($key, $value);
        }
    }

    public static function invalidRequests(): array
    {
        return [
            'missing token' => ['POST', null, 403],
            'empty token' => ['POST', '', 403],
            'invalid token' => ['POST', 'attacker-token', 403],
            'array token' => ['POST', ['attacker-token'], 403],
            'GET with valid token' => ['GET', true, 405],
            'HEAD with valid token' => ['HEAD', true, 405]
        ];
    }

    #[DataProvider('invalidRequests')]
    public function testRejectsInvalidRequest(string $method, mixed $token, int $status): void
    {
        QUI::getRequest()->setMethod($method);
        QUI::getRequest()->request->replace(['_csrf' => $token === true ? CsrfToken::get() : $token]);
        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode($status);
        ProfileSecurity::assertValidRequest();
    }

    public function testAcceptsCoreTokenAndRejectsItInAnotherSession(): void
    {
        QUI::getRequest()->setMethod('POST');
        QUI::getRequest()->request->replace(['_csrf' => CsrfToken::get()]);
        ProfileSecurity::assertValidRequest();
        QUI::getSession()->remove(CsrfToken::SESSION_KEY);
        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode(403);
        ProfileSecurity::assertValidRequest();
    }

    public static function authenticationStates(): array
    {
        return [
            'absent' => [null],
            'expired' => [['uuid' => 'user-a', 'time' => -601]],
            'future' => [['uuid' => 'user-a', 'time' => 60]],
            'wrong user' => [['uuid' => 'user-b', 'time' => 0]],
            'untrusted time type' => [['uuid' => 'user-a', 'time' => '0']]
        ];
    }

    #[DataProvider('authenticationStates')]
    public function testRejectsMissingOrInvalidRecentAuthentication(?array $authentication): void
    {
        if (is_int($authentication['time'] ?? null)) {
            $authentication['time'] += time();
        }

        QUI::getSession()->set(ProfileSecurity::RECENT_AUTH_SESSION_KEY, $authentication);
        $User = $this->createMock(QUI\Interfaces\Users\User::class);
        $User->method('getUUID')->willReturn('user-a');
        QUI::getSession()->set('uid', 'user-a');
        QUI::getSession()->set('auth', 1);
        QUI::getSession()->set('auth-primary', 1);
        QUI::getSession()->set('auth-secondary', 1);
        $this->expectException(QUI\FrontendUsers\Exception::class);
        $this->expectExceptionCode(403);
        ProfileSecurity::assertRecentAuthentication($User);
    }
}
