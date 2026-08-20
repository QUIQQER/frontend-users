<?php

namespace QUI\FrontendUsers\Tests\Support;

use QUI;
use QUI\Projects\Project;
use QUI\Verification\Utils;
use RuntimeException;

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
        $Connection = QUI::getDataBaseConnection();
        self::$siteId = random_int(700000000, 799999999);
        $Connection->insert($Project->table(), [
            'id' => self::$siteId,
            'name' => 'phpunit-frontend-users-verifier-' . bin2hex(random_bytes(6)),
            'title' => 'PHPUnit Frontend Users Verifier',
            'type' => Utils::SITE_TYPE_VERIFIER,
            'active' => 1,
            'deleted' => 0,
            'c_date' => date('Y-m-d H:i:s'),
            'e_date' => date('Y-m-d H:i:s'),
            'c_user' => '5',
            'e_user' => '5',
            'order_field' => 1
        ]);
        $Connection->insert($Project->table() . '_relations', [
            'parent' => 1,
            'child' => self::$siteId
        ]);
    }

    public static function tearDown(): void
    {
        if (self::$Project === null || self::$siteId === null) {
            return;
        }

        $Connection = QUI::getDataBaseConnection();
        $Connection->delete(self::$Project->table() . '_relations', ['child' => self::$siteId]);
        $Connection->delete(self::$Project->table(), ['id' => self::$siteId]);
        self::$Project = null;
        self::$siteId = null;
    }
}
