<?php

namespace QUI\FrontendUsers\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\Controls\Address\Address;
use QUI\FrontendUsers\Controls\Profile;
use QUI\FrontendUsers\Controls\Profile\UserData;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\ProfileSecurity;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Tests\Support\VerificationSiteFixture;
use QUI\Security\CsrfToken;
use QUI\Utils\Singleton;
use ReflectionProperty;

class ProfileSecurityWorkflowTest extends DatabaseTestCase
{
    private array $events;
    private array $instances;
    private array $sessionValues = [];
    private array $mails = [];
    private string $method;
    private QUI\Users\User $User;

    protected function setUp(): void
    {
        parent::setUp();
        $this->method = QUI::getRequest()->getMethod();
        $this->events = QUI::getEvents()->getList();

        // Isolate site-specific hooks that require tables outside these package fixtures.
        foreach ($this->events['onUserCreate'] ?? [] as $event) {
            if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                QUI::getEvents()->removeEvent('onUserCreate', $event['callable']);
            }
        }

        foreach ([CsrfToken::SESSION_KEY, ProfileSecurity::RECENT_AUTH_SESSION_KEY] as $key) {
            $this->sessionValues[$key] = QUI::getSession()->get($key);
            QUI::getSession()->remove($key);
        }

        $Property = new ReflectionProperty(Singleton::class, 'instances');
        $this->instances = $Property->getValue();
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendMail'])->getMock();
        $Handler->method('sendMail')->willReturnCallback(function (array $data, array $recipients): void {
            $this->mails[] = $recipients;
        });
        $Property->setValue(null, array_replace($this->instances, [Handler::class => $Handler]));

        $this->setPackageConfig('registration', 'userWelcomeMail', 0);
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 0);
        $this->setPackageConfig('userProfile', 'useAddressManagement', 1);
        $this->setPackageConfig('profile', 'addressFields', '[]');
        $this->User = $this->createUser(true);
        self::replaceSessionUser($this->User);
        foreach (['user', 'user.data'] as $category) {
            QUI::getPermissionManager()->addPermission([
                'name' => 'quiqqer.frontendUsers.profile.view.' . $category,
                'title' => 'PHPUnit profile access',
                'desc' => '',
                'area' => 'user',
                'type' => 'bool',
                'defaultvalue' => 1
            ]);
        }
        QUI::getPermissionManager()->setPermissions($this->User, [
            'quiqqer.frontendUsers.profile.view.user' => 1,
            'quiqqer.frontendUsers.profile.view.user.data' => 1
        ], QUI::getUsers()->getSystemUser());
        self::assertTrue(QUI\FrontendUsers\Utils::hasPermissionToViewCategory('user', 'data'));
        VerificationSiteFixture::setUp();
    }

    protected function tearDown(): void
    {
        VerificationSiteFixture::tearDown();
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->instances);
        (new ReflectionProperty(QUI\Events\Event::class, 'events'))->setValue(QUI::getEvents(), $this->events);
        QUI::getRequest()->setMethod($this->method);

        foreach ($this->sessionValues as $key => $value) {
            QUI::getSession()->set($key, $value);
        }

        parent::tearDown();
    }

    public static function mutationPaths(): array
    {
        return array_map(static fn(string $path): array => [$path], [
            'native-profile', 'native-create', 'native-edit', 'native-delete',
            'save', 'address/create', 'address/edit', 'address/delete'
        ]);
    }

    #[DataProvider('mutationPaths')]
    public function testMutationRejectsMissingInvalidForeignAndGetTokensBeforeSideEffects(string $path): void
    {
        $Address = $this->User->getStandardAddress();
        self::assertNotNull($Address);
        $beforeUser = $this->userData();
        $beforeAddresses = $this->addressData();
        $foreignToken = CsrfToken::get();
        QUI::getSession()->remove(CsrfToken::SESSION_KEY);

        foreach ([null, 'attacker-token', $foreignToken, CsrfToken::get()] as $index => $token) {
            $values = $this->profileValues() + [
                '_csrf' => $token,
                'addressId' => $Address->getUUID(),
                'profile-save' => 1,
                'createSave' => 1,
                'editSave' => 1,
                'executeDeletion' => 1
            ];
            $this->request($values, $index === 3 ? 'GET' : 'POST');

            try {
                if ($path === 'native-profile') {
                    $this->profile()->getBody();
                } elseif (str_starts_with($path, 'native-')) {
                    $flag = match ($path) {
                        'native-create' => 'createSave',
                        'native-edit' => 'editSave',
                        default => 'executeDeletion'
                    };
                    $_REQUEST = [$flag => 1, 'addressId' => $Address->getUUID()];
                    (new Address())->getBody();
                } else {
                    $this->ajax($path, $values);
                }
                self::fail('A mutation must require a valid POST token: ' . $path);
            } catch (QUI\Exception $Exception) {
                self::assertSame($index === 3 ? 405 : 403, $Exception->getCode());
            }

            self::assertSame($beforeUser, $this->userData());
            self::assertSame($beforeAddresses, $this->addressData());
            self::assertSame(0, $this->verificationCount());
            self::assertSame([], $this->mails);
        }
    }

    public function testValidNativeAndAjaxProfilePostsPersistOrdinaryChangesWithoutRecentLogin(): void
    {
        foreach (['native', 'ajax'] as $transport) {
            $values = ['firstname' => 'Updated ' . $transport, '_csrf' => CsrfToken::get(), 'profile-save' => 1];
            $this->request($values);

            if ($transport === 'native') {
                $html = $this->profile()->getBody();
                self::assertStringContainsString('name="_csrf"', $html);
                self::assertStringContainsString(CsrfToken::get(), $html);
            } else {
                $this->ajax('save', $values);
            }

            self::assertSame($values['firstname'], $this->userData()['firstname']);
            self::assertSame([], $this->mails);
        }
    }

    public function testValidAddressPostsCreateEditAndDelete(): void
    {
        $before = count($this->addressData());
        $values = $this->addressValues() + ['_csrf' => CsrfToken::get()];
        $this->request($values);
        $this->ajax('address/create', $values);
        $rows = $this->addressData();
        self::assertCount($before + 1, $rows);
        $created = array_values(array_filter($rows, static fn(array $row): bool => $row['city'] === 'Test City'))[0];

        $values['addressId'] = $created['uuid'];
        $values['city'] = 'Edited City';
        $this->request($values);
        $this->ajax('address/edit', $values);
        self::assertSame('Edited City', $this->User->getAddress($created['uuid'])->getAttribute('city'));
        $this->request($values);
        $this->ajax('address/delete', $values);
        self::assertCount($before, $this->addressData());
    }

    public function testEmailChangeRequiresRecentLoginEvenWithValidCsrf(): void
    {
        QUI::getSession()->set('uid', $this->User->getUUID());
        QUI::getSession()->set('auth', 1);
        QUI::getSession()->set('auth-primary', 1);
        QUI::getSession()->set('auth-secondary', 1);
        $values = $this->profileValues() + ['_csrf' => CsrfToken::get()];
        $before = $this->userData();

        foreach ([null, time() - 601] as $timestamp) {
            QUI::getSession()->set(ProfileSecurity::RECENT_AUTH_SESSION_KEY, [
                'uuid' => $this->User->getUUID(), 'time' => $timestamp
            ]);
            $this->request($values);

            try {
                $this->ajax('save', $values);
                self::fail('An email change requires recent authentication.');
            } catch (QUI\FrontendUsers\Exception $Exception) {
                self::assertSame(403, $Exception->getCode());
            }

            self::assertSame($before, $this->userData());
            self::assertSame(0, $this->verificationCount());
            self::assertSame([], $this->mails);
        }
    }

    public function testValidNativeAddressPostsCreateEditAndDelete(): void
    {
        $before = count($this->addressData());
        $values = $this->addressValues() + ['_csrf' => CsrfToken::get(), 'createSave' => 1];
        $this->request($values);
        (new Address())->getBody();
        $rows = $this->addressData();
        self::assertCount($before + 1, $rows);
        $created = array_values(array_filter($rows, static fn(array $row): bool => $row['city'] === 'Test City'))[0];

        unset($values['createSave']);
        $values['editSave'] = 1;
        $values['addressId'] = $created['uuid'];
        $values['city'] = 'Native Edit';
        $this->request($values);
        (new Address())->getBody();
        self::assertSame('Native Edit', $this->User->getAddress($created['uuid'])->getAttribute('city'));

        $this->request(['_csrf' => CsrfToken::get(), 'executeDeletion' => 1, 'addressId' => $created['uuid']]);
        (new Address())->getBody();
        self::assertCount($before, $this->addressData());
    }

    public function testCompletedLoginAllowsEmailConfirmationAndNotifiesOldAddress(): void
    {
        $Session = QUI::getSession();
        $Session->set('uid', $this->User->getUUID());
        $Session->set('auth', 1);
        $Session->set('auth-primary', 1);
        $Session->set('auth-secondary', 1);
        ProfileSecurity::onUserLogin($this->User);
        $values = $this->profileValues() + ['_csrf' => CsrfToken::get()];
        $oldEmail = $this->User->getAttribute('email');
        $this->request($values);
        $this->ajax('save', $values);

        self::assertSame(1, $this->verificationCount());
        self::assertSame([[$values['emailNew']], [$oldEmail]], $this->mails);
        self::assertSame($oldEmail, $this->userData()['email']);
    }

    public function testIncompleteLoginDoesNotGrantRecentAuthentication(): void
    {
        QUI::getSession()->set('uid', $this->User->getUUID());
        QUI::getSession()->set('auth-primary', 1);
        QUI::getSession()->set('auth', 0);
        ProfileSecurity::onUserLogin($this->User);
        self::assertFalse(QUI::getSession()->get(ProfileSecurity::RECENT_AUTH_SESSION_KEY));
    }

    public function testConfiguredMfaMustBeCompleteAndRemainAuthenticated(): void
    {
        $Config = QUI::$Conf;
        self::assertNotNull($Config);
        QUI::$Conf = clone $Config;
        QUI::$Conf->setValue('auth_settings', 'secondary_frontend', 1);

        try {
            $Session = QUI::getSession();
            $Session->set('uid', $this->User->getUUID());
            $Session->set('auth', 1);
            $Session->set('auth-primary', 1);
            $Session->set('auth-secondary', 0);
            ProfileSecurity::onUserLogin($this->User);
            self::assertFalse($Session->get(ProfileSecurity::RECENT_AUTH_SESSION_KEY));

            $Session->set('auth-secondary', 1);
            ProfileSecurity::onUserLogin($this->User);
            ProfileSecurity::assertRecentAuthentication($this->User);
            $Session->set('auth-secondary', 0);
            $this->expectException(QUI\FrontendUsers\Exception::class);
            ProfileSecurity::assertRecentAuthentication($this->User);
        } finally {
            QUI::$Conf = $Config;
        }
    }

    public function testNativeEmailChangeWithoutRecentLoginShowsErrorAndCreatesNoVerification(): void
    {
        $before = $this->userData();
        $this->request($this->profileValues() + ['_csrf' => CsrfToken::get(), 'profile-save' => 1]);
        self::assertStringContainsString('role="alert"', $this->profile()->getBody());
        self::assertSame($before, $this->userData());
        self::assertSame(0, $this->verificationCount());
        self::assertSame([], $this->mails);
    }

    public function testAddressViewsRenderCoreTokenForNativeAndAjaxForms(): void
    {
        $Address = $this->User->getStandardAddress();
        self::assertNotNull($Address);

        foreach ([[], ['create' => 1], ['edit' => $Address->getUUID()], ['delete' => $Address->getUUID()]] as $values) {
            $this->request($values, 'GET');
            $html = (new Address())->getBody();
            self::assertStringContainsString('data-name="csrf-token"', $html);
            self::assertStringContainsString(CsrfToken::get(), $html);
        }
    }

    private function profile(): Profile
    {
        return new Profile([
            'category' => 'user', 'settings' => 'data', 'User' => $this->User,
            'Site' => QUI::getProjectManager()->getStandard()->get(1)
        ]);
    }

    private function request(array $values, string $method = 'POST'): void
    {
        $_REQUEST = $values;
        $_POST = $method === 'POST' ? $values : [];
        QUI::getRequest()->setMethod($method);
        QUI::getRequest()->request->replace($values);
    }

    private function ajax(string $path, array $values): void
    {
        require dirname(__DIR__, 2) . '/ajax/frontend/profile/' . $path . '.php';
        $name = 'package_quiqqer_frontend-users_ajax_frontend_profile_' . str_replace('/', '_', $path);
        $registration = QUI::getAjax()->getRegisteredCallables()[$name];
        $arguments = $values + ['category' => 'user', 'settings' => 'data', 'data' => json_encode($values)];
        $params = array_map(static fn(string $key): mixed => $arguments[$key] ?? '', $registration['params']);
        ($registration['callable'])(...$params);
    }

    private function profileValues(): array
    {
        return ['firstname' => 'Attacker', 'emailNew' => 'attacker@example.invalid'];
    }

    private function addressValues(): array
    {
        return [
            'company' => 'Test Ltd', 'firstname' => 'Test', 'lastname' => 'User',
            'street_no' => 'Test Street 1', 'zip' => '12345', 'city' => 'Test City', 'country' => 'de'
        ];
    }

    private function userData(): array
    {
        return self::getConnection()->fetchAssociative(
            'SELECT firstname, email FROM ' . QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table())
            . ' WHERE uuid = :uuid',
            ['uuid' => $this->User->getUUID()]
        );
    }

    private function addressData(): array
    {
        return self::getConnection()->fetchAllAssociative(
            'SELECT * FROM ' . QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::tableAddress())
            . ' ORDER BY uuid'
        );
    }

    private function verificationCount(): int
    {
        return (int)self::getConnection()->fetchOne(
            'SELECT COUNT(*) FROM ' . QUI\Utils\Doctrine::quoteIdentifier(
                QUI::getDBTableName('quiqqer_verification_processes')
            )
        );
    }
}
