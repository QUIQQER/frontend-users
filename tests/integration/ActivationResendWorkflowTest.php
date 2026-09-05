<?php

namespace QUI\FrontendUsers\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\ActivationResend;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Tests\Support\VerificationSiteFixture;
use QUI\Utils\Singleton;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationStatus;
use QUI\Verification\VerificationRepository;
use ReflectionProperty;
use RuntimeException;

class ActivationResendWorkflowTest extends DatabaseTestCase
{
    private array $events;
    private array $instances;
    private array $server;
    private array $mails = [];
    private bool $mailFailure = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->events = QUI::getEvents()->getList();
        foreach ($this->events['onUserCreate'] ?? [] as $event) {
            if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                QUI::getEvents()->removeEvent('onUserCreate', $event['callable']);
            }
        }
        $this->server = QUI::getRequest()->server->all();
        $this->source('192.0.2.1');
        $Property = new ReflectionProperty(Singleton::class, 'instances');
        $this->instances = $Property->getValue();
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendMail'])->getMock();
        $Handler->method('sendMail')->willReturnCallback(function (
            array $data,
            array $recipients,
            string $template,
            array $variables
        ): void {
            $this->mails[] = ['recipients' => $recipients, 'variables' => $variables];
            if ($this->mailFailure) {
                throw new RuntimeException('Simulated activation mail failure');
            }
        });
        $Property->setValue(null, array_replace($this->instances, [Handler::class => $Handler]));
        VerificationSiteFixture::setUp();
        self::replaceSessionUser(QUI::getUsers()->getNobody());
    }

    protected function tearDown(): void
    {
        VerificationSiteFixture::tearDown();
        QUI::getRequest()->server->replace($this->server);
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->instances);
        (new ReflectionProperty(QUI\Events\Event::class, 'events'))->setValue(QUI::getEvents(), $this->events);
        parent::tearDown();
    }

    public function testRepeatedRequestsPreserveTheLinkAndSendAtMostOnce(): void
    {
        $User = $this->pendingUser();
        $before = $this->verification($User);
        for ($i = 1; $i <= 4; $i++) {
            $this->source('192.0.2.' . $i);
            self::assertTrue($this->request(' ' . $User->getAttribute('email') . ' '));
        }
        self::assertCount(1, $this->mails);
        self::assertSame([$User->getAttribute('email')], $this->mails[0]['recipients']);
        self::assertStringContainsString($before->getVerificationUrl(), $this->mails[0]['variables']['body']);
        self::assertEquals($before, $this->verification($User));
    }

    public function testSourceQuotaAppliesAcrossAccountsAndForwardedHeaders(): void
    {
        $First = $this->pendingUser();
        $Second = $this->pendingUser();
        self::assertTrue($this->request($First->getAttribute('email')));
        QUI::getRequest()->server->set('HTTP_X_FORWARDED_FOR', '198.51.100.2');
        self::assertTrue($this->request($Second->getAttribute('email')));
        self::assertCount(1, $this->mails);
        $this->source('192.0.2.2');
        self::assertTrue($this->request($Second->getAttribute('email')));
        self::assertCount(2, $this->mails);
    }

    public function testUnknownAccountsConsumeTheSourceQuota(): void
    {
        $User = $this->pendingUser();
        self::assertTrue($this->request('unknown@example.invalid'));
        self::assertTrue($this->request($User->getAttribute('email')));
        self::assertSame([], $this->mails);
    }

    public static function ineligibleAccounts(): array
    {
        return array_map(static fn(string $state): array => [$state], [
            'active', 'expired-date', 'expired-status', 'verified', 'missing', 'unknown',
            'invalid-email', 'invalid-input', 'wrong-user', 'wrong-handler', 'missing-project'
        ]);
    }

    #[DataProvider('ineligibleAccounts')]
    public function testIneligibleRequestsReturnTheSameAcknowledgementWithoutMail(string $state): void
    {
        $User = $this->pendingUser();
        $verification = $this->verification($User);
        $Repository = new VerificationRepository();
        $email = $User->getAttribute('email');
        switch ($state) {
            case 'active':
                $User->setPassword('phpunit-resend-password', QUI::getUsers()->getSystemUser());
                $User->activate('', QUI::getUsers()->getSystemUser());
                break;
            case 'expired-date':
                self::getConnection()->update($this->verificationTable(), [
                    'validUntilDate' => '2000-01-01 00:00:00'
                ], ['uuid' => $verification->uuid]);
                break;
            case 'expired-status':
            case 'verified':
                $verification->status = $state === 'verified'
                    ? VerificationStatus::VERIFIED : VerificationStatus::EXPIRED;
                $Repository->update($verification);
                break;
            case 'missing':
                $Repository->delete($verification);
                break;
            case 'unknown':
                $email = 'unknown@example.invalid';
                break;
            case 'invalid-email':
                $email = 'invalid';
                break;
            case 'invalid-input':
                $email = ['unexpected'];
                break;
            case 'wrong-user':
            case 'missing-project':
                $verification->customData[$state === 'wrong-user' ? 'uuid' : 'project'] = '';
                $Repository->delete($verification);
                $Repository->insert($verification, new QUI\FrontendUsers\ActivationLinkVerification());
                break;
            case 'wrong-handler':
                self::getConnection()->update($this->verificationTable(), [
                    'verificationHandler' => QUI\FrontendUsers\EmailConfirmLinkVerification::class
                ], ['uuid' => $verification->uuid]);
                break;
        }
        $before = self::getConnection()->fetchAllAssociative('SELECT * FROM ' . $this->verificationTable());
        self::assertTrue($this->request($email));
        self::assertSame([], $this->mails);
        self::assertSame($before, self::getConnection()->fetchAllAssociative('SELECT * FROM ' . $this->verificationTable()));
    }

    public function testMailFailurePreservesTheTokenAndDoesNotAllowImmediateRetries(): void
    {
        $User = $this->pendingUser();
        $before = $this->verification($User);
        $this->mailFailure = true;
        self::assertTrue($this->request($User->getAttribute('email')));
        $this->source('192.0.2.2');
        self::assertTrue($this->request($User->getAttribute('email')));
        self::assertCount(1, $this->mails);
        self::assertEquals($before, $this->verification($User));
        self::assertTrue($this->verification($User)->isValid());
    }

    public function testExpiredCooldownAllowsTheSameLinkToBeSentAgainAndCleansReservations(): void
    {
        $User = $this->pendingUser();
        $before = $this->verification($User);
        self::assertTrue($this->request($User->getAttribute('email')));
        self::getConnection()->createQueryBuilder()
            ->update(QUI\Utils\Doctrine::quoteIdentifier(ActivationResend::table()))
            ->set('expires_at', ':expires_at')
            ->setParameter('expires_at', time() - 1)
            ->executeStatement();
        self::assertTrue($this->request($User->getAttribute('email')));
        self::assertCount(2, $this->mails);
        self::assertEquals($before, $this->verification($User));
        $rows = self::getConnection()->fetchAllAssociative('SELECT * FROM ' . ActivationResend::table());
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $row['subject_key']);
            self::assertGreaterThan(time(), (int)$row['expires_at']);
        }
    }

    public function testThrottleStorageFailureFailsClosed(): void
    {
        $User = $this->pendingUser();
        $before = $this->verification($User);
        $Connection = self::getConnection();
        $Unavailable = $this->getMockBuilder(\Doctrine\DBAL\Connection::class)
            ->disableOriginalConstructor()->onlyMethods(['createQueryBuilder'])->getMock();
        $Unavailable->method('createQueryBuilder')->willThrowException(new RuntimeException('Throttle storage unavailable'));
        $Property = new ReflectionProperty(QUI::class, 'QueryBuilder');
        $Property->setValue(null, $Unavailable);
        try {
            self::assertTrue($this->request($User->getAttribute('email')));
            self::assertSame([], $this->mails);
        } finally {
            $Property->setValue(null, $Connection);
        }
        self::assertEquals($before, $this->verification($User));
    }

    public function testMissingSourceFailsClosed(): void
    {
        $User = $this->pendingUser();
        QUI::getRequest()->server->remove('REMOTE_ADDR');
        self::assertTrue($this->request($User->getAttribute('email')));
        self::assertSame([], $this->mails);
    }

    public function testIpv6TextVariantsShareTheSourceQuota(): void
    {
        $First = $this->pendingUser();
        $Second = $this->pendingUser();
        $this->source('2001:db8::1');
        self::assertTrue($this->request($First->getAttribute('email')));
        $this->source('2001:0db8:0000:0000:0000:0000:0000:0001');
        self::assertTrue($this->request($Second->getAttribute('email')));
        self::assertCount(1, $this->mails);
    }

    private function pendingUser(): QUI\Users\User
    {
        $User = $this->createUser();
        $Registrar = new Registrar();
        $Registrar->setProject(QUI::getRewrite()->getProject());
        self::assertTrue(Handler::getInstance()->sendActivationMail($User, $Registrar));
        $this->mails = [];
        return $User;
    }

    private function verification(QUI\Users\User $User): LinkVerification
    {
        $verification = (new VerificationRepository())->findByIdentifier('activate-' . $User->getUUID());
        self::assertInstanceOf(LinkVerification::class, $verification);
        return $verification;
    }

    private function verificationTable(): string
    {
        return QUI::getDBTableName(VerificationRepository::TBL_VERIFICATION_PROCESSES);
    }

    private function source(string $ip): void
    {
        QUI::getRequest()->server->set('REMOTE_ADDR', $ip);
    }

    private function request(mixed $email): bool
    {
        require dirname(__DIR__, 2) . '/ajax/frontend/auth/resendActivationMail.php';
        $callable = QUI::getAjax()->getRegisteredCallables()[
            'package_quiqqer_frontend-users_ajax_frontend_auth_resendActivationMail'
        ]['callable'];
        return $callable($email);
    }
}
