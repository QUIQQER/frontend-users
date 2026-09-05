<?php

namespace QUI\FrontendUsers\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\Cache\Manager as CacheManager;
use QUI\FrontendUsers\Controls\Profile\DeleteAccount;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\ProfileSecurity;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Tests\Support\VerificationSiteFixture;
use QUI\FrontendUsers\UserDeleteConfirmLinkVerification;
use QUI\Security\CsrfToken;
use QUI\Utils\Singleton;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;
use QUI\Verification\Enum\VerificationStatus;
use QUI\Verification\VerificationRepository;
use QUI\Verification\Verifier;
use ReflectionProperty;
use Stash\Driver\Ephemeral;
use Stash\Pool;

class AccountDeletionWorkflowTest extends DatabaseTestCase
{
    private array $events;
    private array $instances;
    private QUI\Users\User $User;
    private VerificationRepository $Repository;
    private int $deletions = 0;
    private int $mails = 0;
    private ?Pool $previousCachePool;
    private ?QUI\Config $previousCacheConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCachePool = CacheManager::$Stash;
        $this->previousCacheConfig = CacheManager::$Config;
        CacheManager::$Config = clone CacheManager::getConfig();
        CacheManager::$Config->setValue('general', 'nocache', 0);
        CacheManager::$Stash = new Pool(new Ephemeral());
        $this->events = QUI::getEvents()->getList();

        // Foreign lifecycle hooks require application tables outside this package's fixtures.
        foreach (['onUserCreate', 'onUserDelete', 'onUserDisable', 'onQuiqqerFrontendUsersUserDelete'] as $name) {
            foreach ($this->events[$name] ?? [] as $event) {
                if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                    QUI::getEvents()->removeEvent($name, $event['callable']);
                }
            }
        }

        $Property = new ReflectionProperty(Singleton::class, 'instances');
        $this->instances = $Property->getValue();
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendMail'])->getMock();
        $Handler->method('sendMail')->willReturnCallback(function (): void {
            $this->mails++;
        });
        $Property->setValue(null, array_replace($this->instances, [Handler::class => $Handler]));
        $this->setPackageConfig('registration', 'userWelcomeMail', 0);
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 0);
        $this->setPackageConfig('userProfile', 'userDeleteMode', 'delete');
        $this->User = $this->createUser(true);
        QUI::getPermissionManager()->addPermission([
            'name' => 'quiqqer.users.delete_self', 'type' => 'bool', 'defaultvalue' => 1
        ]);
        QUI::getPermissionManager()->setPermissions($this->User, [
            'quiqqer.users.delete_self' => 1
        ], QUI::getUsers()->getSystemUser());
        $this->Repository = new VerificationRepository();
        VerificationSiteFixture::setUp();
        QUI::getEvents()->addEvent('onQuiqqerFrontendUsersUserDelete', function (): void {
            $this->deletions++;
        });
        $this->login($this->User);
        $this->request();
    }

    protected function tearDown(): void
    {
        VerificationSiteFixture::tearDown();
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->instances);
        $this->restoreEvents($this->events);
        try {
            parent::tearDown();
        } finally {
            CacheManager::$Stash = $this->previousCachePool;
            CacheManager::$Config = $this->previousCacheConfig;
        }
    }

    public static function modes(): array
    {
        return [['delete'], ['wipe'], ['destroy']];
    }

    #[DataProvider('modes')]
    public function testMailLinkPrefetchNeverChangesUserOrLogsOutVisitor(string $mode): void
    {
        $this->setPackageConfig('userProfile', 'userDeleteMode', $mode);
        $verification = $this->verification(false);
        $before = $this->userData();
        $Other = $this->createUser(true);
        $Handler = new UserDeleteConfirmLinkVerification();

        foreach ([QUI::getUsers()->getNobody(), $Other, $this->User] as $Visitor) {
            $this->login($Visitor);

            foreach (['GET', 'HEAD'] as $method) {
                $this->request($method);
                if ($verification->status === VerificationStatus::PENDING) {
                    (new Verifier())->verifyVerificationCode($verification, $verification->verificationCode);
                }
                $Handler->onSuccess($verification);
                $Handler->onError($verification, VerificationErrorReason::ALREADY_VERIFIED);
                self::assertSame($before, $this->userData());
                self::assertSame($Visitor, QUI::getUserBySession());
                self::assertSame(0, $this->deletions);
            }
        }

        self::assertNotSame('', $Handler->getSuccessMessage($verification));
        self::assertSame('', $Handler->getErrorMessage($verification, VerificationErrorReason::EXPIRED));
        self::assertGreaterThan(0, $Handler->getValidDuration($verification));
        self::assertSame(VerificationStatus::VERIFIED, $this->Repository->findByUuid($verification->uuid)->status);
    }

    public static function rejectedRequests(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'anonymous', 'other-user', 'no-recent-auth', 'expired-auth', 'incomplete-mfa',
            'GET', 'HEAD', 'missing-csrf', 'invalid-csrf', 'foreign-csrf',
            'pending', 'expired-link', 'cancelled', 'replaced', 'wrong-purpose', 'wrong-owner'
        ]);
    }

    #[DataProvider('rejectedRequests')]
    public function testRejectsUnsafeConfirmationWithoutUserSideEffects(string $case): void
    {
        $verification = $this->verification($case !== 'pending');
        $before = $this->userData();
        $Config = QUI::$Conf;

        try {
            switch ($case) {
                case 'anonymous':
                    $this->login(QUI::getUsers()->getNobody());
                    break;
                case 'other-user':
                    $this->login($this->createUser(true));
                    break;
                case 'no-recent-auth':
                    QUI::getSession()->remove(ProfileSecurity::RECENT_AUTH_SESSION_KEY);
                    break;
                case 'expired-auth':
                    QUI::getSession()->set(ProfileSecurity::RECENT_AUTH_SESSION_KEY, [
                        'uuid' => $this->User->getUUID(), 'time' => time() - 601
                    ]);
                    break;
                case 'incomplete-mfa':
                    QUI::$Conf = clone $Config;
                    QUI::$Conf->setValue('auth_settings', 'secondary_frontend', 1);
                    QUI::getSession()->set('auth-secondary', 0);
                    break;
                case 'GET':
                case 'HEAD':
                    $this->request($case);
                    break;
                case 'missing-csrf':
                    QUI::getRequest()->request->remove('_csrf');
                    break;
                case 'invalid-csrf':
                    QUI::getRequest()->request->set('_csrf', 'attacker-token');
                    break;
                case 'foreign-csrf':
                    QUI::getSession()->remove(CsrfToken::SESSION_KEY);
                    break;
                case 'expired-link':
                    self::getConnection()->update(QUI::getDBTableName('quiqqer_verification_processes'), [
                        'validUntilDate' => (new DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s')
                    ], ['uuid' => $verification->uuid]);
                    break;
                case 'cancelled':
                    $this->Repository->delete($verification);
                    break;
                case 'replaced':
                    $this->verification(false);
                    break;
                case 'wrong-purpose':
                    self::getConnection()->update(QUI::getDBTableName('quiqqer_verification_processes'), [
                        'verificationHandler' => QUI\FrontendUsers\ActivationLinkVerification::class
                    ], ['uuid' => $verification->uuid]);
                    break;
                case 'wrong-owner':
                    $this->Repository->delete($verification);
                    $verification->customData['uuid'] = 'another-user';
                    $this->Repository->insert($verification, new UserDeleteConfirmLinkVerification());
                    break;
            }

            try {
                (new UserDeleteConfirmLinkVerification())->confirmDeletion($verification);
                self::fail('Unsafe confirmation must be rejected: ' . $case);
            } catch (QUI\Exception $Exception) {
                self::assertSame(in_array($case, ['GET', 'HEAD'], true) ? 405 : 403, $Exception->getCode());
            }

            self::assertSame($before, $this->userData());
            self::assertSame(0, $this->deletions);
        } finally {
            QUI::$Conf = $Config;
        }
    }

    #[DataProvider('modes')]
    public function testRecentOwnerCanConfirmThroughProfilePostOnce(string $mode): void
    {
        $this->setPackageConfig('userProfile', 'userDeleteMode', $mode);
        $verification = $this->verification();
        $Control = new DeleteAccount();
        $Control->onSave();
        $stored = $this->userData();

        if ($mode === 'destroy') {
            self::assertFalse($stored);
        } else {
            self::assertSame(-1, (int)$stored['active']);
        }

        self::assertSame(1, $this->deletions);
        self::assertNull($this->Repository->findByUuid($verification->uuid));
        self::assertFalse(QUI::getSession()->get('auth'));
        self::assertStringContainsString('role="status"', $Control->getBody());

        $this->login($this->User);
        $this->request();
        try {
            (new UserDeleteConfirmLinkVerification())->confirmDeletion($verification);
            self::fail('The request can only be used once.');
        } catch (QUI\Exception $Exception) {
            self::assertSame(403, $Exception->getCode());
        }
        self::assertSame(1, $this->deletions);
    }

    public function testDeletionCheckCanVetoFinalConfirmationWithoutConsumingRequest(): void
    {
        $verification = $this->verification();
        $before = $this->userData();
        QUI::getEvents()->addEvent('onQuiqqerFrontendUsersDeleteAccountCheck', static function (): void {
            throw new QUI\FrontendUsers\Exception('Deletion vetoed.', 403);
        });

        try {
            (new DeleteAccount())->onSave();
            self::fail('The final deletion must respect the deletion check.');
        } catch (QUI\Exception $Exception) {
            self::assertStringContainsString('Deletion vetoed.', $Exception->getMessage());
        }

        self::assertSame($before, $this->userData());
        self::assertNotNull($this->Repository->findByUuid($verification->uuid));
        self::assertSame(0, $this->deletions);
    }

    public static function templates(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('templates')]
    public function testProfileOffersConfirmationOnlyAfterMailAndAllowsCancellation(bool $basic): void
    {
        if ($basic) {
            define('QUIQQER_CONTROL_TEMPLATE_USE_BASIC', true);
        }

        $verification = $this->verification(false);
        self::assertStringNotContainsString('data-name="delete-account-confirm"', (new DeleteAccount())->getBody());
        (new Verifier())->verifyVerificationCode($verification, $verification->verificationCode);
        (new UserDeleteConfirmLinkVerification())->onSuccess($verification);
        $html = (new DeleteAccount())->getBody();
        self::assertStringContainsString('data-name="delete-account-confirm"', $html);
        self::assertStringContainsString('name="deleteAccountAction" value="confirm"', $html);
        self::assertStringNotContainsString($verification->verificationCode, $html);
        self::assertStringContainsString('data-name="delete-account-cancel"', $html);

        QUI::getRequest()->request->set('deleteAccountAction', 'cancel');
        (new DeleteAccount())->onSave();
        self::assertNull($this->Repository->findByUuid($verification->uuid));
        self::assertSame(1, (int)$this->userData()['active']);
        self::assertSame(0, $this->deletions);
    }

    public function testResendReplacesMailConfirmationAndDoesNotDeleteAccount(): void
    {
        $old = $this->verification();
        QUI::getRequest()->request->set('deleteAccountAction', 'resend');
        (new DeleteAccount())->onSave();
        $current = $this->Repository->findByIdentifier('confirmdelete-' . $this->User->getUUID());
        self::assertNotNull($current);
        self::assertNotSame($old->uuid, $current->uuid);
        self::assertSame(VerificationStatus::PENDING, $current->status);
        self::assertSame(2, $this->mails);
        self::assertSame(1, (int)$this->userData()['active']);
    }

    public function testNewAndPreviouslyOpenedLinksLeadToProfileWithoutSecrets(): void
    {
        $verification = $this->verification();
        $Project = QUI::getRewrite()->getProject() ?? QUI::getProjectManager()->getStandard();
        $siteId = random_int(850000000, 899999999);
        self::getConnection()->insert($Project->table(), [
            'id' => $siteId, 'name' => self::TEST_PREFIX . 'delete-profile', 'title' => 'PHPUnit Profile',
            'type' => Handler::SITE_TYPE_PROFILE, 'active' => 1, 'deleted' => 0,
            'c_date' => date('Y-m-d H:i:s'), 'e_date' => date('Y-m-d H:i:s'),
            'c_user' => '5', 'e_user' => '5', 'order_field' => 1
        ]);
        self::getConnection()->insert($Project->table() . '_relations', ['parent' => 1, 'child' => $siteId]);
        $this->login(QUI::getUsers()->getNobody());
        $Handler = new UserDeleteConfirmLinkVerification();
        $url = $Handler->getOnSuccessRedirectUrl($verification);
        self::assertNotNull($url);
        self::assertStringEndsWith('/user/deleteaccount', $url);
        self::assertStringNotContainsString($verification->verificationCode, $url);
        self::assertStringNotContainsString($this->User->getUUID(), $url);
        self::assertSame($url, $Handler->getOnErrorRedirectUrl($verification, VerificationErrorReason::ALREADY_VERIFIED));
        self::assertSame(1, (int)$this->userData()['active']);
        self::assertTrue(QUI::getUsers()->isNobodyUser(QUI::getUserBySession()));
    }

    private function verification(bool $verified = true): LinkVerification
    {
        Handler::getInstance()->sendDeleteUserConfirmationMail(
            $this->User,
            QUI::getRewrite()->getProject() ?? QUI::getProjectManager()->getStandard()
        );
        $verification = $this->Repository->findByIdentifier('confirmdelete-' . $this->User->getUUID());
        self::assertInstanceOf(LinkVerification::class, $verification);

        if ($verified) {
            (new Verifier())->verifyVerificationCode($verification, $verification->verificationCode);
        }

        return $verification;
    }

    private function login(QUI\Interfaces\Users\User $User): void
    {
        self::replaceSessionUser($User);
        QUI::getSession()->set('uid', $User->getUUID());
        foreach (['auth', 'auth-primary', 'auth-secondary'] as $key) {
            QUI::getSession()->set($key, 1);
        }
        ProfileSecurity::onUserLogin($User);
    }

    private function request(string $method = 'POST'): void
    {
        QUI::getRequest()->setMethod($method);
        QUI::getRequest()->request->replace(['_csrf' => CsrfToken::get(), 'deleteAccountAction' => 'confirm']);
    }

    private function userData(): array | false
    {
        return self::getConnection()->fetchAssociative(
            'SELECT * FROM ' . QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()) . ' WHERE uuid = :uuid',
            ['uuid' => $this->User->getUUID()]
        );
    }
}
