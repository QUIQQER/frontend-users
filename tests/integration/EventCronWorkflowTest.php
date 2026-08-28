<?php

namespace QUI\FrontendUsers\Tests\Integration;

use QUI;
use QUI\FrontendUsers\Cron;
use QUI\FrontendUsers\Events;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use ReflectionMethod;

class EventCronWorkflowTest extends DatabaseTestCase
{
    public function testChangePasswordRequestResolvesProjectProfileSite(): void
    {
        $ProfileSite = $this->createMock(QUI\Projects\Site::class);
        $ProfileSite->method('getUrlRewrittenWithHost')->willReturn('https://example.test/profile');

        $Project = $this->createMock(QUI\Projects\Project::class);
        $Project->expects(self::once())
            ->method('getSitesIds')
            ->with([
                'where' => [
                    'type' => Handler::SITE_TYPE_PROFILE
                ],
                'limit' => 1
            ])
            ->willReturn([['id' => 42]]);
        $Project->expects(self::once())
            ->method('get')
            ->with(42)
            ->willReturn($ProfileSite);

        $Rewrite = $this->createMock(QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturn($Project);

        $Method = new ReflectionMethod(Events::class, 'getChangePasswordRedirectUrl');

        self::assertSame(
            'https://example.test/profile/user/changepassword',
            $Method->invoke(null, $Rewrite, '/.well-known/change-password')
        );
        self::assertNull($Method->invoke(null, $Rewrite, '/.well-known/other'));
    }

    public function testChangePasswordRequestWithoutProfileSiteIsNotHandled(): void
    {
        $Project = $this->createMock(QUI\Projects\Project::class);
        $Project->method('getSitesIds')->willReturn([]);

        $Rewrite = $this->createMock(QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturn($Project);

        $Method = new ReflectionMethod(Events::class, 'getChangePasswordRedirectUrl');

        self::assertNull($Method->invoke(null, $Rewrite, '.well-known/change-password'));
    }

    public function testFrontendSiteTypesAreMarkedAsNonCacheable(): void
    {
        foreach (
            [
            Handler::SITE_TYPE_REGISTRATION,
            Handler::SITE_TYPE_PROFILE,
            Handler::SITE_TYPE_LOGIN
            ] as $type
        ) {
            $Site = $this->createMock(QUI\Interfaces\Projects\Site::class);
            $Site->method('getAttribute')->with('type')->willReturn($type);
            $Site->expects(self::once())->method('setAttribute')->with('nocache', 1);
            Events::onSiteInit($Site);
        }

        $Site = $this->createMock(QUI\Interfaces\Projects\Site::class);
        $Site->method('getAttribute')->willReturnCallback(
            static fn(string $name): mixed => $name === 'type' ? 'quiqqer/core:types/article' : false
        );
        $Site->expects(self::never())->method('setAttribute');
        Events::onSiteInit($Site);
        Events::onSiteSave($Site);
    }

    public function testUserLifecycleEventsAndAutoLoginPersistExpectedState(): void
    {
        $this->setPackageConfig('registration', 'userWelcomeMail', 0);
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 0);
        $User = $this->createUser(true);
        $User->setAttribute(Handler::USER_ATTR_USER_ACTIVATION_REQUIRED, true);
        $User->save(QUI::getUsers()->getSystemUser());

        Events::onUserActivate($User);
        self::assertFalse((bool)$User->getAttribute(Handler::USER_ATTR_USER_ACTIVATION_REQUIRED));

        $_SERVER['HTTP_USER_AGENT'] = 'frontend-users-phpunit';
        Events::autoLogin($User, false);
        self::assertSame($User->getUUID(), QUI::getSession()->get('uid'));
        self::assertSame(1, QUI::getSession()->get('auth'));
        self::assertTrue((bool)$User->getAttribute(Handler::USER_ATTR_ACTIVATION_LOGIN_EXECUTED));

        $row = self::getConnection()->createQueryBuilder()
            ->select('user_agent', 'secHash', 'lastvisit')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()))
            ->where('uuid = :uuid')
            ->setParameter('uuid', $User->getUUID())
            ->executeQuery()
            ->fetchAssociative();
        self::assertSame('frontend-users-phpunit', $row['user_agent']);
        self::assertNotSame('', $row['secHash']);
        self::assertGreaterThan(0, (int)$row['lastvisit']);

        Events::onUserCreate($User);
        self::assertNotNull($User->getAttribute('quiqqer.frontendUsers.useGravatarIcon'));
        Events::onUserDelete($User);
    }

    public function testTemplateEventsAddFrontendAssets(): void
    {
        $Template = $this->createMock(QUI\Template::class);
        $Template->expects(self::once())
            ->method('extendHeader')
            ->with(self::stringContains('bin/style.css'));

        ob_start();
        Events::onTemplateGetHeader($Template);
        $output = (string)ob_get_clean();
        self::assertStringContainsString('onAjaxLogin', $output);

        $User = $this->createUser(true, ['quiqqer.set.new.password' => 1]);
        self::replaceSessionUser($User);
        $Template = $this->createMock(QUI\Template::class);
        ob_start();
        Events::onTemplateGetHeader($Template);
        $output = (string)ob_get_clean();
        self::assertStringContainsString('mustChange: true', $output);
        self::assertStringContainsString($User->getUUID(), $output);
    }

    public function testCronDeletesOnlySufficientlyOldFrontendRegistration(): void
    {
        $this->setPackageConfig('registration', 'deleteInactiveUserAfterDays', 20000);
        $User = $this->createUser();
        $User->setAttribute(Handler::USER_ATTR_USER_ACTIVATION_REQUIRED, true);
        $User->save(QUI::getUsers()->getSystemUser());

        self::getConnection()->update(
            QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()),
            ['regdate' => 0],
            ['uuid' => $User->getUUID()]
        );
        $User->setAttribute('regdate', 0);

        Cron::deleteUnverifiedInactiveUsers();

        $count = self::getConnection()->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()))
            ->where('uuid = :uuid')
            ->setParameter('uuid', $User->getUUID())
            ->executeQuery()
            ->fetchOne();
        self::assertSame(0, (int)$count);
    }

    public function testDefaultSetupHelpersPopulateEmptyPackageSettings(): void
    {
        $Config = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $this->setPackageConfig('registration', 'addressFields', $Config->get('registration', 'addressFields'));
        $this->setPackageConfig('profile', 'addressFields', $Config->get('profile', 'addressFields'));
        $this->setPackageConfig('login', 'authenticators', '');
        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([]));

        foreach (
            [
            'setAddressDefaultSettings',
            'setAuthenticatorsDefaultSettings',
            'setRegistrarsDefaultSettings'
            ] as $method
        ) {
            (new ReflectionMethod(Events::class, $method))->invoke(null);
        }

        self::assertNotEmpty($Config->get('registration', 'addressFields'));
        self::assertNotEmpty($Config->get('profile', 'addressFields'));
        self::assertNotEmpty($Config->get('login', 'authenticators'));
        self::assertStringContainsString(
            base64_encode(Registrar::class),
            (string)$Config->get('registrars', 'registrarSettings')
        );
        Events::checkUserMediaFolder();
    }

    public function testAutoLoginEligibilityGuardsDoNotMutateIneligibleUser(): void
    {
        $User = $this->createUser(true);
        Events::autoLogin($User);
        self::assertEmpty(QUI::getSession()->get('uid'));

        $User->setAttribute(Handler::USER_ATTR_REGISTRAR, 'Missing\\Registrar');
        Events::autoLogin($User);
        self::assertEmpty(QUI::getSession()->get('uid'));

        $this->setPackageConfig('registrars', 'registrarSettings', json_encode([
            base64_encode(Registrar::class) => [
                'active' => true,
                'activationMode' => Handler::ACTIVATION_MODE_MANUAL,
                'displayPosition' => 1
            ]
        ]));
        $this->setPackageConfig('registration', 'autoLoginOnActivation', 1);
        $User->setAttribute(Handler::USER_ATTR_REGISTRAR, Registrar::class);
        $User->save(QUI::getUsers()->getSystemUser());
        Events::autoLogin($User);
        self::assertEmpty(QUI::getSession()->get('uid'));
    }

    public function testRemainingEventGuardsAndTemplateCollectorAreSideEffectFree(): void
    {
        $this->setPackageConfig('registration', 'userWelcomeMail', 1);
        $User = $this->createUser();
        $Project = QUI::getRewrite()->getProject();
        $User->setAttributes([
            Handler::USER_ATTR_REGISTRATION_PROJECT => $Project->getName(),
            Handler::USER_ATTR_REGISTRATION_PROJECT_LANG => $Project->getLang()
        ]);
        Events::sendWelcomeMail($User);
        self::assertFalse((bool)$User->getAttribute(Handler::USER_ATTR_WELCOME_MAIL_SENT));

        $Collector = new QUI\Smarty\Collector();
        Events::onTemplateEnd($Collector, $this->createMock(QUI\Template::class));
        self::assertStringContainsString('dataLayerTracking.js', $Collector->getContent());

        $OtherPackage = $this->createMock(QUI\Package\Package::class);
        $OtherPackage->method('getName')->willReturn('quiqqer/other-package');
        Events::onPackageInstall($OtherPackage);
        Events::onPackageSetup($OtherPackage);
    }
}
