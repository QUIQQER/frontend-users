<?php

namespace QUI\FrontendUsers\Tests\Integration;

use DateTimeImmutable;
use QUI;
use QUI\FrontendUsers\Controls\Profile;
use QUI\FrontendUsers\Controls\Bricks\AuthContent;
use QUI\FrontendUsers\Controls\Profile\ChangePassword;
use QUI\FrontendUsers\Controls\Profile\DeleteAccount;
use QUI\FrontendUsers\Controls\Profile\TwoFactorAuthentication;
use QUI\FrontendUsers\Controls\Profile\UserAvatar;
use QUI\FrontendUsers\Controls\Profile\UserAvatarUpload;
use QUI\FrontendUsers\Controls\UserIcon;
use QUI\FrontendUsers\Rest\Provider;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Utils;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationStatus;
use QUI\Verification\Interface\VerificationRepositoryInterface;

class AuxiliaryWorkflowTest extends DatabaseTestCase
{
    public function testProfileUtilitiesResolveCategoriesTranslationsAndUrls(): void
    {
        $packages = Utils::getFrontendUsersPackages();
        self::assertNotEmpty($packages);
        $categories = Utils::getProfileCategories();
        self::assertNotEmpty($categories);
        self::assertNotEmpty(Utils::getProfileCategorySettings());
        self::assertIsArray(Utils::getProfileBarCategorySettings());

        $category = reset($categories);
        self::assertIsArray($category);
        self::assertSame($category, Utils::getProfileCategory($category['name']));
        if ($category['items'] !== []) {
            $setting = reset($category['items']);
            self::assertSame($setting, Utils::getProfileSetting($category['name'], $setting['name']));
            self::assertTrue(Utils::hasPermissionToViewCategory($category['name'], $setting['name']));
            $Control = Utils::getProfileSettingControl($category['name'], $setting['name']);
            self::assertTrue($Control === null || $Control instanceof QUI\FrontendUsers\Controls\Profile\ControlInterface);
        }

        $translated = Utils::loadTranslationForCategories([[
            'name' => 'phpunit',
            'title' => ['quiqqer/frontend-users', 'control.profile.title'],
            'items' => [[
                'name' => 'entry',
                'title' => ['quiqqer/frontend-users', 'control.profile.title']
            ]]
        ]]);
        self::assertIsString($translated[0]['title']);
        self::assertIsString($translated[0]['items'][0]['title']);
        $withUrls = Utils::setUrlsToCategorySettings($translated);
        self::assertTrue($withUrls === [] || isset($withUrls[0]['items'][0]['url']));

        self::assertIsBool(Utils::isCaptchaModuleInstalled());
        self::assertStringContainsString('?s=1&d=mm', Utils::getGravatarUrl('USER@example.invalid', 0));
        self::assertStringContainsString('?s=2048&d=mm', Utils::getGravatarUrl('USER@example.invalid', 3000));
    }

    public function testProfilePasswordAvatarAndIconActionsUseAuthenticatedUser(): void
    {
        $this->setPackageConfig('userProfile', 'useGravatar', 1);
        $this->setPackageConfig('userProfile', 'userAvatarUploadAllowed', 0);
        $this->setPackageConfig('profileBar', 'defaultAvatar', 'fa-user-circle');
        $User = $this->createUser(true);
        self::replaceSessionUser($User);

        QUI::getRequest()->request->replace([
            'passwordOld' => 'phpunit-frontend-users-password',
            'passwordNew' => 'phpunit-new-frontend-password'
        ]);
        (new ChangePassword(['User' => $User]))->onSave();

        QUI::getRequest()->request->replace(['useGravatar' => '1']);
        $Avatar = new UserAvatar();
        $Avatar->onSave();
        self::assertTrue((bool)$User->getAttribute('quiqqer.frontendUsers.useGravatarIcon'));
        self::assertNotSame('', $Avatar->getBody());

        $Icon = new UserIcon(['User' => $User, 'iconWidth' => 64, 'iconHeight' => 64]);
        self::assertNotSame('', $Icon->getBody());
        QUI::getRequest()->request->replace(['useGravatar' => '0']);
        $Avatar->onSave();
        self::assertNotSame('', $Icon->getBody());
        $User->setAttribute('avatar', '/phpunit/missing/avatar.png');
        self::assertNotSame('', $Icon->getBody());
        $User->setAttribute('avatar', '');
        self::assertSame('', (new UserIcon(['User' => false]))->getBody());
        self::assertSame('', (new UserIcon(['User' => 'invalid']))->getBody());

        $Site = QUI::getRewrite()->getSite();
        $Profile = new Profile(['User' => $User, 'Site' => $Site]);
        self::assertSame($User, $Profile->getUser());
        self::assertSame($Site, $Profile->getSite());
        self::assertSame($Site, (new Profile(['User' => $User]))->getSite());

        $categories = Utils::getProfileCategorySettings();
        foreach ($categories as $category) {
            foreach ($category['items'] as $setting) {
                $Control = Utils::getProfileSettingControl($category['name'], $setting['name']);
                if (!$Control) {
                    continue;
                }

                QUI::getRequest()->setMethod('POST');
                QUI::getRequest()->request->replace([
                    'profile-save' => 1,
                    '_csrf' => QUI\Security\CsrfToken::get()
                ]);
                $SavingProfile = new Profile([
                    'User' => $User,
                    'Site' => $Site,
                    'category' => $category['name'],
                    'settings' => $setting['name']
                ]);
                self::assertIsString($SavingProfile->getBody());
                break 2;
            }
        }

        QUI::getRequest()->request->replace([
            'passwordOld' => 'definitely-wrong',
            'passwordNew' => 'phpunit-another-password'
        ]);
        try {
            (new ChangePassword(['User' => $User]))->onSave();
            self::fail('A wrong current password must be rejected.');
        } catch (QUI\FrontendUsers\Exception $Exception) {
            self::assertNotSame('', $Exception->getMessage());
        }

        QUI::getRequest()->request->replace([
            'passwordOld' => 123,
            'passwordNew' => 456
        ]);

        try {
            (new ChangePassword(['User' => $User]))->onSave();
            self::fail('Non-string password request data must be rejected.');
        } catch (QUI\FrontendUsers\Exception $Exception) {
            self::assertSame(
                'Frontend users ChangePassword::onSave: Invalid password request data.',
                $Exception->getMessage()
            );
        }

        try {
            (new Profile())->getUser();
            self::fail('A profile without a user must reject getUser().');
        } catch (QUI\FrontendUsers\Exception $Exception) {
            self::assertNotSame('', $Exception->getMessage());
        }
    }

    public function testDeleteAccountCancellationAndTwoFactorNoOpAreSafe(): void
    {
        $User = $this->createUser(true);
        self::replaceSessionUser($User);
        $Verification = $this->createVerification($User->getUUID());
        $Repository = $this->createMock(VerificationRepositoryInterface::class);
        $Repository->expects(self::exactly(2))
            ->method('findByIdentifier')
            ->with('confirmdelete-' . $User->getUUID())
            ->willReturn($Verification);
        $Repository->expects(self::once())->method('delete')->with($Verification);
        $Control = new DeleteAccount([], $Repository);
        self::assertNotSame('', $Control->getBody());

        QUI::getRequest()->request->replace(['deleteAccountAction' => 'cancel']);
        $Control->onSave();
        DeleteAccount::checkDeleteAccount();

        $TwoFactor = new TwoFactorAuthentication();
        $TwoFactor->onSave();
        self::assertNotSame('', $TwoFactor->getBody());
    }

    public function testEmailBlacklistAndRestProviderMetadata(): void
    {
        $this->setPackageConfig('registration', 'emailBlacklist', json_encode([
            '*@blocked.invalid',
            'specific@*',
            'exact@example.invalid',
            'invalid-pattern'
        ]));
        self::assertTrue(Utils::isEmailBlacklisted('any@blocked.invalid'));
        self::assertTrue(Utils::isEmailBlacklisted('specific@somewhere.invalid'));
        self::assertTrue(Utils::isEmailBlacklisted('exact@example.invalid'));
        self::assertFalse(Utils::isEmailBlacklisted('allowed@example.invalid'));
        self::assertFalse(Utils::isEmailBlacklisted('not-an-email'));

        $Provider = new Provider();
        self::assertSame('FrontendUsers', $Provider->getName());
        self::assertNotSame('', $Provider->getTitle());
        self::assertStringEndsWith('docs/openapi.json', (string)$Provider->getOpenApiDefinitionFile());

        $App = \Slim\Factory\AppFactory::create();
        $Server = $this->createMock(QUI\REST\Server::class);
        $Server->method('getSlim')->willReturn($App);
        $Provider->register($Server);
        self::assertNotEmpty($App->getRouteCollector()->getRoutes());
    }

    public function testAvatarUploadRejectsMissingConfiguredMediaFolder(): void
    {
        $this->setPackageConfig('userProfile', 'userAvatarFolder', '/phpunit/missing/avatar/folder');
        $User = $this->createUser(true);
        $file = tempnam(sys_get_temp_dir(), 'frontend-users-avatar-');
        self::assertNotFalse($file);

        try {
            (new UserAvatarUpload(['User' => $User]))->onFileFinish($file, []);
            self::fail('A missing media folder must reject the avatar upload.');
        } catch (QUI\Exception $Exception) {
            self::assertNotSame('', $Exception->getMessage());
        } finally {
            @unlink($file);
        }
    }

    public function testAvatarUploadStoresAndRemovesUploadedImage(): void
    {
        $User = $this->createUser(true);
        $Project = QUI::getRewrite()->getProject();
        self::assertNotNull($Project);
        $this->setPackageConfig(
            'userProfile',
            'userAvatarFolder',
            $Project->getMedia()->get(1)->getUrl()
        );
        $file = tempnam(sys_get_temp_dir(), 'frontend-users-avatar-');
        self::assertNotFalse($file);
        $pngFile = $file . '.png';
        rename($file, $pngFile);
        file_put_contents(
            $pngFile,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );
        $UploadedImage = null;

        try {
            (new UserAvatarUpload(['User' => $User]))->onFileFinish($pngFile, []);
            $avatarUrl = (string)$User->getAttribute('avatar');
            self::assertNotSame('', $avatarUrl);
            $UploadedImage = QUI\Projects\Media\Utils::getImageByUrl($avatarUrl);
            self::assertGreaterThan(0, $UploadedImage->getId());
        } finally {
            if ($UploadedImage) {
                $UploadedImage->delete(QUI::getUsers()->getSystemUser());
            }

            @unlink($pngFile);
        }
    }

    public function testAuthContentSelectsGuestAndGroupSpecificLocalizedContent(): void
    {
        $lang = QUI::getLocale()->getCurrent();
        $Guest = new AuthContent([
            'content_guest' => json_encode([$lang => 'Welcome [username]'])
        ]);
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        self::assertStringContainsString('Welcome', $Guest->getBody());

        $User = $this->createUser(true);
        $Group = $this->createGroup();
        $User->addToGroup($Group->getUUID());
        $User->save(QUI::getUsers()->getSystemUser());
        self::replaceSessionUser($User);
        $Control = new AuthContent([
            'groups' => $Group->getUUID(),
            'content_in_groups' => json_encode([$lang => 'Hello [username]']),
            'content_not_in_groups' => json_encode([$lang => 'No access'])
        ]);
        self::assertStringContainsString('Hello', $Control->getBody());

        $OtherGroup = $this->createGroup();
        $Control->setAttribute('groups', $OtherGroup->getUUID());
        self::assertStringContainsString('No access', $Control->getBody());
        $Control->setAttribute('content_not_in_groups', '{invalid-json');
        self::assertIsString($Control->getBody());
    }

    private function createVerification(string $userUuid): LinkVerification
    {
        $now = new DateTimeImmutable();

        return new LinkVerification(
            'phpunit-delete-verification',
            'confirmdelete-' . $userUuid,
            'phpunit-code',
            $now,
            $now,
            0,
            'https://example.invalid/verify',
            VerificationStatus::PENDING,
            ['uuid' => $userUuid],
            $now->modify('+1 hour')
        );
    }
}
