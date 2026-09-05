<?php

namespace QUI\FrontendUsers\Tests\Integration;

use QUI;
use QUI\Cache\Manager as CacheManager;
use QUI\FrontendUsers\Controls\Profile\UserAvatar;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use Stash\Driver\Ephemeral;
use Stash\Pool;

class AjaxWorkflowTest extends DatabaseTestCase
{
    public function testRegistrarAndSettingsEndpointsExposeValidatedRuntimeData(): void
    {
        $this->configureRegistrar();
        $this->setPackageConfig('registration', 'emailBlacklist', json_encode(['*@blocked.invalid']));
        $User = $this->createUser();

        self::assertTrue($this->call('frontend/registrars/validateEmailSyntax.php', [
            'email' => 'valid@example.invalid'
        ]));
        self::assertFalse($this->call('frontend/registrars/validateEmailSyntax.php', [
            'email' => 'invalid'
        ]));
        self::assertTrue($this->call('frontend/registrars/userExists.php', [
            'username' => $User->getUsername()
        ]));
        self::assertFalse($this->call('frontend/registrars/userExists.php', [
            'username' => self::TEST_PREFIX . 'missing'
        ]));
        self::assertTrue($this->call('frontend/registrars/emailExists.php', [
            'email' => $User->getAttribute('email')
        ]));
        self::assertFalse($this->call('frontend/registrars/emailExists.php', [
            'email' => 'invalid'
        ]));
        self::assertTrue($this->call('frontend/registrars/emailBlacklisted.php', [
            'email' => 'user@blocked.invalid'
        ]));
        self::assertFalse($this->call('frontend/registrars/emailBlacklisted.php', [
            'email' => 'user@allowed.invalid'
        ]));

        $registrars = $this->call('settings/getRegistrars.php');
        $registrarTypes = array_column($registrars, 'type');
        self::assertContains(Registrar::class, $registrarTypes);
        $registrar = $registrars[array_search(Registrar::class, $registrarTypes, true)];
        self::assertNotEmpty($registrar['activationModes']);

        $authenticators = $this->call('settings/getAuthenticators.php');
        self::assertIsArray($authenticators);
    }

    public function testAuthenticationAndRegistrarControlEndpointsReturnObservableResults(): void
    {
        $this->configureRegistrar();
        $Project = QUI::getRewrite()->getProject();
        self::assertNotNull($Project);
        $encodedProject = json_encode([
            'name' => $Project->getName(),
            'lang' => $Project->getLang()
        ]);

        self::assertFalse($this->call('frontend/auth/existsUnverifiedActivation.php', [
            'userId' => 'missing-user'
        ]));
        self::assertTrue($this->call('frontend/auth/resendActivationMail.php', [
            'email' => 'missing@example.invalid'
        ]));
        self::assertSame('', $this->call('frontend/login/getAuthenticator.php', [
            'authenticator' => 'Missing\\Authenticator'
        ]));
        self::assertIsString($this->call('frontend/login/getControl.php', [
            'authenticators' => '[]',
            'mail' => false,
            'passwordReset' => false
        ]));
        self::assertFalse($this->call('frontend/login/getLoginRedirect.php', [
            'project' => $encodedProject
        ]));
        self::assertSame('', $this->call('frontend/registrars/getRegistrationLink.php', [
            'project' => $encodedProject
        ]));
        self::assertIsString($this->call('frontend/registrars/getSignInControl.php'));
        self::assertIsString($this->call('frontend/registrars/getControl.php', [
            'registrar' => hash('sha256', Registrar::class)
        ]));
    }

    public function testAddressEndpointsPersistAndRenderAuthenticatedUserAddresses(): void
    {
        $this->setPackageConfig('userProfile', 'useAddressManagement', 1);
        $this->setAddressFieldConfiguration();
        $User = $this->createUser(true);
        self::replaceSessionUser($User);
        $before = count($User->getAddressList());
        $data = [
            'firstname' => 'Ajax',
            'lastname' => 'Address',
            'street_no' => 'Ajax Street 1',
            'zip' => '12345',
            'city' => 'Ajax City',
            'country' => 'DE',
            'email' => 'ajax-address@example.invalid'
        ];

        self::assertNull($this->call('frontend/profile/address/create.php', [
            'data' => json_encode($data)
        ]));
        $addresses = $User->getAddressList();
        self::assertCount($before + 1, $addresses);
        $Address = end($addresses);
        self::assertInstanceOf(QUI\Users\Address::class, $Address);

        self::assertIsString($this->call('frontend/profile/address/control.php'));
        self::assertIsString($this->call('frontend/profile/address/getCreate.php'));
        self::assertIsString($this->call('frontend/profile/address/getEdit.php', [
            'addressId' => $Address->getUUID()
        ]));
        self::assertIsString($this->call('frontend/profile/address/getDelete.php', [
            'addressId' => $Address->getUUID()
        ]));

        $data['city'] = 'Edited Ajax City';
        self::assertNull($this->call('frontend/profile/address/edit.php', [
            'addressId' => $Address->getUUID(),
            'data' => json_encode($data)
        ]));
        self::assertSame('Edited Ajax City', $Address->getAttribute('city'));

        self::assertNull($this->call('frontend/profile/address/delete.php', [
            'addressId' => $Address->getUUID()
        ]));
        $storedAddress = self::getConnection()->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::tableAddress()))
            ->where('uuid = :uuid')
            ->setParameter('uuid', $Address->getUUID())
            ->executeQuery()
            ->fetchOne();
        self::assertSame(0, (int)$storedAddress);
    }

    public function testProfileAndTermsEndpointsHandleUnavailableConfigurationSafely(): void
    {
        $User = $this->createUser(true);
        self::replaceSessionUser($User);
        $Project = QUI::getRewrite()->getProject();
        self::assertNotNull($Project);
        $encodedProject = json_encode([
            'name' => $Project->getName(),
            'lang' => $Project->getLang()
        ]);

        self::assertIsString($this->call('frontend/profile/getControl.php', [
            'category' => '',
            'settings' => '',
            'project' => $encodedProject,
            'siteId' => 1,
            'menu' => true
        ]));
        self::assertSame([], $this->call('frontend/profile/getProfileBarCategories.php'));

        $terms = $this->call('frontend/termsOfUse.php');
        self::assertFalse($terms['required']);
        self::assertSame('', $terms['label']);
        self::assertNull($terms['termsOfUse']);

        $result = $this->callRaw('frontend/profile/save.php', [
            'category' => 'missing',
            'settings' => 'missing',
            'data' => '{}'
        ]);
        self::assertArrayHasKey('Exception', $result);
    }

    public function testRegistrationProfileAndTermsEndpointsExposeConfiguredWorkflows(): void
    {
        $Group = $this->createGroup();
        $this->configureRegistrar();
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_REQUIRED);
        $this->setPackageConfig('registration', 'passwordInput', Handler::PASSWORD_INPUT_NONE);
        $this->setPackageConfig('registration', 'fullnameInput', Handler::FULLNAME_INPUT_FULLNAME_OPTIONAL);
        $this->setPackageConfig('registration', 'addressInput', 0);
        $this->setPackageConfig('registration', 'useCaptcha', 0);
        $this->setPackageConfig('registration', 'termsOfUseRequired', 0);
        $this->setPackageConfig('registration', 'defaultGroups', $Group->getUUID());
        $this->setPackageConfig('registration', 'forcePasswordReset', 0);
        $this->setPackageConfig('registration', 'sendInfoMailOnRegistrationTo', '');
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 0);
        $this->setPackageConfig('registration', 'reloadOnSuccess', 0);
        $this->setPackageConfig('registration', 'autoRedirectOnSuccess', json_encode([]));
        $this->setPackageConfig('registration', 'emailBlacklist', json_encode([]));
        $suffix = bin2hex(random_bytes(5));
        $username = 'fu-ajax-' . $suffix;
        $registration = $this->call('frontend/register.php', [
            'registrar' => hash('sha256', Registrar::class),
            'data' => json_encode([
                'username' => $username,
                'email' => $username . '@example.invalid',
                'firstname' => 'Ajax',
                'lastname' => 'Registration'
            ]),
            'registrars' => json_encode([Registrar::class]),
            'isSignUpRegistration' => false
        ]);
        self::assertIsString($registration['html']);
        self::assertNotFalse($registration['userId'], json_encode($registration));
        self::assertSame(Registrar::class, $registration['registrarType']);
        $RegisteredUser = QUI::getUsers()->get($registration['userId']);
        self::assertSame($username, $RegisteredUser->getUsername());
        self::assertTrue($RegisteredUser->isInGroup($Group->getUUID()));

        self::replaceSessionUser($RegisteredUser);
        self::assertNull($this->call('frontend/profile/checkDeleteAccount.php'));

        $Project = QUI::getRewrite()->getProject();
        self::assertNotNull($Project);
        $profileSiteId = random_int(800000000, 849999999);
        self::getConnection()->insert($Project->table(), [
            'id' => $profileSiteId,
            'name' => 'phpunit-profile',
            'title' => 'PHPUnit Profile',
            'type' => Handler::SITE_TYPE_PROFILE,
            'active' => 1,
            'deleted' => 0,
            'c_date' => date('Y-m-d H:i:s'),
            'e_date' => date('Y-m-d H:i:s'),
            'c_user' => '5',
            'e_user' => '5',
            'order_field' => 1
        ]);
        self::getConnection()->insert($Project->table() . '_relations', [
            'parent' => 1,
            'child' => $profileSiteId
        ]);
        self::assertIsArray($this->call('frontend/profile/getProfileBarCategories.php'));

        $siteLink = 'index.php?project=' . $Project->getName()
            . '&lang=' . $Project->getLang()
            . '&id=1';
        $lang = $Project->getLang();
        $this->setPackageConfig('registration', 'termsOfUseRequired', 1);
        $this->setPackageConfig('registration', 'termsOfUseSite', json_encode([$lang => $siteLink]));
        $this->setPackageConfig('registration', 'privacyPolicySite', json_encode([$lang => $siteLink]));
        $terms = $this->call('frontend/termsOfUse.php');
        self::assertTrue($terms['required']);
        self::assertSame(1, $terms['termsOfUse']);
        self::assertSame(1, $terms['privacyPolicy']);
        self::assertNotSame('', $terms['label']);

        $this->setPackageConfig('registration', 'privacyPolicySite', json_encode([]));
        self::assertNotSame('', $this->call('frontend/termsOfUse.php')['label']);
        $this->setPackageConfig('registration', 'termsOfUseSite', json_encode([]));
        $this->setPackageConfig('registration', 'privacyPolicySite', json_encode([$lang => $siteLink]));
        self::assertNotSame('', $this->call('frontend/termsOfUse.php')['label']);

        $this->setPackageConfig('registration', 'termsOfUseSite', json_encode([$lang => $siteLink]));
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $_POST = [];
        $_REQUEST = [];
        self::assertNotSame(
            '',
            (new QUI\FrontendUsers\Controls\Registration(['registrars' => [Registrar::class]]))->getBody()
        );
        self::assertNotSame(
            '',
            (new QUI\FrontendUsers\Controls\RegistrationSignUp(['registrars' => [Registrar::class]]))->getBody()
        );

        // Never publish the avatar-only fixture into the shared installation cache.
        $previousCachePool = CacheManager::$Stash;
        $previousCacheConfig = CacheManager::$Config;
        CacheManager::$Config = clone CacheManager::getConfig();
        CacheManager::$Config->setValue('general', 'nocache', 0);
        CacheManager::$Stash = new Pool(new Ephemeral());
        CacheManager::set('package/quiqqer/frontendUsers/profileCategories', [
            'user' => [
                'name' => 'user',
                'title' => ['quiqqer/frontend-users', 'profile.user.title'],
                'items' => [[
                    'name' => 'avatar',
                    'title' => ['quiqqer/frontend-users', 'profile.avatar.title'],
                    'control' => UserAvatar::class
                ]]
            ]
        ]);

        try {
            $this->setPackageConfig('userProfile', 'useGravatar', 1);
            $SystemUser = QUI::getUsers()->getSystemUser();
            self::replaceSessionUser($SystemUser);
            self::assertNull($this->call('frontend/profile/save.php', [
                'category' => 'user',
                'settings' => 'avatar',
                'data' => json_encode(['useGravatar' => '1'])
            ]));
            self::assertTrue((bool)$SystemUser->getAttribute('quiqqer.frontendUsers.useGravatarIcon'));
        } finally {
            CacheManager::$Stash = $previousCachePool;
            CacheManager::$Config = $previousCacheConfig;
        }
    }

    private function configureRegistrar(): void
    {
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode(Registrar::class) => [
                'active' => true,
                'activationMode' => Handler::ACTIVATION_MODE_MANUAL,
                'displayPosition' => 1
            ]
        ]));
    }

    private function setAddressFieldConfiguration(): void
    {
        $fields = [];

        foreach (['firstname', 'lastname', 'street_no', 'zip', 'city', 'country', 'email'] as $field) {
            $fields[$field] = ['show' => true, 'required' => false];
        }

        $this->setPackageConfig('profile', 'addressFields', json_encode($fields));
    }

    private function call(string $file, array $values = []): mixed
    {
        if (
            in_array($file, [
            'frontend/profile/save.php',
            'frontend/profile/address/create.php',
            'frontend/profile/address/edit.php',
            'frontend/profile/address/delete.php'
            ], true)
        ) {
            QUI::getRequest()->setMethod('POST');
            QUI::getRequest()->request->set('_csrf', QUI\Security\CsrfToken::get());
        }

        $result = $this->callRaw($file, $values);
        self::assertArrayNotHasKey('Exception', $result, $file . ': ' . json_encode($result));

        return $result['result'];
    }

    /** @return array<string, mixed> */
    private function callRaw(string $file, array $values = []): array
    {
        $path = dirname(__DIR__, 2) . '/ajax/' . $file;
        require $path;
        $function = 'package_quiqqer_frontend-users_ajax_' . str_replace(['/', '.php'], ['_', ''], $file);
        $callables = QUI::getAjax()->getRegisteredCallables();

        self::assertArrayHasKey($function, $callables);

        $params = [];

        foreach ($callables[$function]['params'] as $name) {
            $params[] = $values[$name] ?? '';
        }

        $_REQUEST = array_merge($_REQUEST, $values);

        try {
            return ['result' => ($callables[$function]['callable'])(...$params)];
        } catch (\Exception $Exception) {
            return ['Exception' => ['message' => $Exception->getMessage()]];
        }
    }
}
