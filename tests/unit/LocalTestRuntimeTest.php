<?php

namespace QUI\FrontendUsers\Tests\Unit;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\Tests\Support\DatabaseEnvironment;

class LocalTestRuntimeTest extends TestCase
{
    public function testRuntimeMatchesTheSelectedEnvironment(): void
    {
        $source = dirname(__DIR__, 5) . '/';
        if (DatabaseEnvironment::usesCiDatabase()) {
            self::assertSame(realpath($source), realpath(CMS_DIR));
            self::assertSame(realpath($source . 'etc'), realpath(ETC_DIR));
            return;
        }

        self::assertInstanceOf(SQLitePlatform::class, QUI::getDataBaseConnection()->getDatabasePlatform());
        self::assertNotSame(realpath($source), realpath(CMS_DIR));
        self::assertStringStartsWith(CMS_DIR, ETC_DIR);
        self::assertStringStartsWith(CMS_DIR, VAR_DIR);
        self::assertStringStartsWith(CMS_DIR, USR_DIR);
        self::assertSame(0700, fileperms(CMS_DIR) & 0777);

        $sourceConfig = $source . 'etc/plugins/quiqqer/frontend-users.ini.php';
        $hash = hash_file('sha256', $sourceConfig);
        $Package = QUI::getPackage('quiqqer/frontend-users');
        $Config = $Package->getConfig();
        self::assertNotNull($Config);
        $Config->setValue('registration', 'throttleLookupIpLimit', 123);
        $Config->save();
        self::assertSame($hash, hash_file('sha256', $sourceConfig));
        self::assertSame(123, (int)(new QUI\Config(ETC_DIR . 'plugins/quiqqer/frontend-users.ini.php'))
            ->get('registration', 'throttleLookupIpLimit'));

        $mailFile = $Package->getVarDir() . 'send_user_mails';
        self::assertStringStartsWith(VAR_DIR, $mailFile);
        file_put_contents($mailFile, 'test status');
        self::assertSame('test status', file_get_contents($mailFile));
        unlink($mailFile);
        mkdir(CMS_DIR . 'media/test-runtime', 0700, true);
        self::assertDirectoryDoesNotExist($source . 'media/test-runtime');
    }

    public function testLocalConnectionsIgnoreExternalDatabaseOverrides(): void
    {
        if (DatabaseEnvironment::usesCiDatabase()) {
            self::assertSame(DatabaseEnvironment::MODE_CI_DATABASE, DatabaseEnvironment::getMode());
            return;
        }

        foreach (['FRONTEND_USERS_RESEND_TEST_DATABASE', 'FRONTEND_USERS_REGISTRATION_THROTTLE_TEST_DATABASE'] as $name) {
            $previous = getenv($name);
            putenv($name . '={"driver":"pdo_mysql","host":"must-not-be-contacted.invalid"}');
            try {
                $Connection = DatabaseEnvironment::createConnection();
                self::assertInstanceOf(SQLitePlatform::class, $Connection->getDatabasePlatform());
                self::assertSame(1, (int)$Connection->fetchOne('SELECT 1'));
                $Connection->close();
            } finally {
                putenv($previous === false ? $name : $name . '=' . $previous);
            }
        }
    }
}
