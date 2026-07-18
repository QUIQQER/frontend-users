<?php

namespace QUI\FrontendUsers\Tests\Support;

use QUI;
use QUI\Permissions\Permission;
use QUI\Projects\Project;
use QUI\Projects\Site\Edit;
use QUI\Verification\Utils;
use ReflectionProperty;
use RuntimeException;
use Throwable;

final class VerificationSiteFixture
{
    private static ?Project $Project = null;
    private static ?int $siteId = null;

    public static function setUp(): void
    {
        $Project = QUI::getRewrite()->getProject() ?? QUI::getProjectManager()->getStandard();

        if ($Project === null) {
            throw new RuntimeException('A project is required for the verification test fixture.');
        }

        $siteIds = $Project->getSitesIds([
            'where' => [
                'type' => Utils::SITE_TYPE_VERIFIER
            ]
        ]);

        if ($siteIds !== []) {
            return;
        }

        self::$Project = $Project;
        register_shutdown_function([self::class, 'tearDown']);

        self::withSystemUser(static function () use ($Project): void {
            $Root = $Project->firstChild()->getEdit();

            if ($Root === null) {
                throw new RuntimeException('The project root site is not editable.');
            }

            $siteId = $Root->createChild(
                [
                    'name' => 'phpunit-frontend-users-verifier-' . bin2hex(random_bytes(6)),
                    'title' => 'PHPUnit Frontend Users Verifier'
                ],
                [],
                QUI::getUsers()->getSystemUser()
            );
            self::$siteId = $siteId;

            $VerifierSite = new Edit($Project, $siteId);
            $VerifierSite->setAttribute('type', Utils::SITE_TYPE_VERIFIER);
            $VerifierSite->save(QUI::getUsers()->getSystemUser());
            $VerifierSite->activate(QUI::getUsers()->getSystemUser());
        });
    }

    public static function tearDown(): void
    {
        if (self::$Project === null || self::$siteId === null) {
            return;
        }

        try {
            self::withSystemUser(static function (): void {
                if (self::$Project === null || self::$siteId === null) {
                    return;
                }

                $VerifierSite = new Edit(self::$Project, self::$siteId);
                $VerifierSite->delete();
                (new Edit(self::$Project, self::$siteId))->destroy();
            });
        } catch (Throwable) {
            // Cleanup must not hide the actual PHPUnit result.
        } finally {
            self::$Project = null;
            self::$siteId = null;
        }
    }

    private static function withSystemUser(callable $Callback): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $SessionProperty = new ReflectionProperty($Users, 'Session');
        $previousSessionUser = $SessionProperty->getValue($Users);
        $PermissionProperty = new ReflectionProperty(Permission::class, 'User');
        $previousPermissionUser = $PermissionProperty->getValue();

        $SessionProperty->setValue($Users, $SystemUser);
        $PermissionProperty->setValue(null, $SystemUser);

        try {
            $Callback();
        } finally {
            $SessionProperty->setValue($Users, $previousSessionUser);
            $PermissionProperty->setValue(null, $previousPermissionUser);
        }
    }
}
