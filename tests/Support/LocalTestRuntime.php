<?php

namespace QUI\FrontendUsers\Tests\Support;

use Doctrine\DBAL\DriverManager;
use QUI;
use ReflectionProperty;
use RuntimeException;

/** Keeps local tests away from the installation's database and writable files. */
final class LocalTestRuntime
{
    private static ?string $directory = null;

    public static function prepare(): void
    {
        if (DatabaseEnvironment::usesCiDatabase()) {
            return;
        }

        foreach (['CMS_DIR', 'ETC_DIR', 'VAR_DIR', 'USR_DIR'] as $constant) {
            if (defined($constant)) {
                throw new RuntimeException('Local tests must initialize their runtime before QUIQQER.');
            }
        }

        $source = dirname(__DIR__, 5) . '/';
        $config = parse_ini_file($source . 'etc/conf.ini.php', true);
        if ($config === false) {
            throw new RuntimeException('Cannot read the QUIQQER configuration for local tests.');
        }

        $directory = sys_get_temp_dir() . '/frontend-users-runtime-' . bin2hex(random_bytes(16)) . '/';
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create the local test runtime.');
        }
        self::$directory = $directory;
        register_shutdown_function(static function (): void {
            // Run after QUIQQER's shutdown callbacks, including failed bootstraps.
            self::registerCleanup();
        });

        // Each PHPUnit process owns its directory; workers copy their parent's settings.
        $settingsSource = getenv('FRONTEND_USERS_TEST_SETTINGS') ?: $source . 'etc/';
        self::copyDirectory($settingsSource, $directory . 'etc/');
        self::copyDirectory($config['globals']['var_dir'] . 'locale/', $directory . 'var/locale/');
        mkdir($directory . 'var/composer/', 0700, true);
        foreach (['composer.json', 'composer.lock'] as $file) {
            copy($config['globals']['var_dir'] . 'composer/' . $file, $directory . 'var/composer/' . $file);
        }
        mkdir($directory . 'usr/', 0700, true);
        mkdir($directory . 'media/', 0700, true);
        // Share installed source code, while all writable runtime paths stay private.
        if (!symlink($config['globals']['opt_dir'], $directory . 'packages')) {
            throw new RuntimeException('Cannot expose installed packages to the local test runtime.');
        }
        $eventsSource = getenv('FRONTEND_USERS_TEST_EVENTS');
        if ($eventsSource !== false && is_file($eventsSource)) {
            mkdir($directory . 'var/cache/', 0700, true);
            copy($eventsSource, $directory . 'var/cache/events.php');
        }

        define('CMS_DIR', $directory);
        define('ETC_DIR', $directory . 'etc/');
        define('VAR_DIR', $directory . 'var/');
        define('USR_DIR', $directory . 'usr/');
        define('OPT_DIR', $directory . 'packages/');
        define('LIB_DIR', OPT_DIR . 'quiqqer/core/src/');
        putenv('FRONTEND_USERS_TEST_SETTINGS=' . ETC_DIR);

        require_once dirname(__DIR__, 3) . '/core/src/autoload.php';
        $Config = new QUI\Config(ETC_DIR . 'conf.ini.php');
        foreach (['cms_dir' => CMS_DIR, 'var_dir' => VAR_DIR, 'usr_dir' => USR_DIR, 'opt_dir' => OPT_DIR, 'system_changed' => 0] as $key => $value) {
            $Config->setValue('globals', $key, $value);
        }
        $Config->set('db', [
            'driver' => 'pdo_sqlite', 'path' => VAR_DIR . 'bootstrap.sqlite',
            'prfx' => $config['db']['prfx'] ?? ''
        ]);
        $Config->save();
        file_put_contents(ETC_DIR . 'cache.ini.php', ";<?php exit; ?>\n[handlers]\nfilesystem = 1\n");

        $Connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $Connection);
        // Boot the framework without starting installed applications against empty fixtures.
        QUI::$Events = new class extends QUI\Events\Manager {
            public function __construct()
            {
            }

            public function fireEvent(string $event, false|array $args = false, bool $force = false): array
            {
                return [];
            }
        };
    }

    public static function finishBootstrap(): void
    {
        if (self::$directory !== null) {
            QUI::$Events = null;
            $Events = QUI::getEvents();
            putenv('FRONTEND_USERS_TEST_EVENTS=' . VAR_DIR . 'cache/events.php');
            foreach ($Events->getList() as $name => $listeners) {
                foreach ($listeners as $listener) {
                    if (!in_array($listener['package'], ['quiqqer/core', 'quiqqer/frontend-users', 'quiqqer/verification'], true)) {
                        $Events->removeEvent($name, $listener['callable']);
                    }
                }
            }
        }
    }

    private static function registerCleanup(): void
    {
        if (self::$directory === null) {
            return;
        }

        $directory = self::$directory;
        register_shutdown_function(static function () use ($directory): void {
            self::removeDirectory($directory);
        });
    }

    private static function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }
        if (!is_dir($destination) && !mkdir($destination, 0700, true)) {
            throw new RuntimeException('Cannot create a test fixture directory.');
        }
        foreach (new \DirectoryIterator($source) as $Entry) {
            if ($Entry->isDot()) {
                continue;
            }
            // Never copy databases, sockets or links into the local runtime.
            if ($Entry->isLink() || in_array($Entry->getFilename(), ['database', 'localefiles'], true)) {
                continue;
            }
            if ($Entry->isDir()) {
                self::copyDirectory($Entry->getPathname(), $destination . $Entry->getFilename() . '/');
            } elseif ($Entry->isFile() && !copy($Entry->getPathname(), $destination . $Entry->getFilename())) {
                throw new RuntimeException('Cannot copy a local test fixture.');
            }
        }
    }

    private static function removeDirectory(string $directory): void
    {
        foreach (new \DirectoryIterator($directory) as $Entry) {
            if ($Entry->isDot()) {
                continue;
            }
            if ($Entry->isDir() && !$Entry->isLink()) {
                self::removeDirectory($Entry->getPathname());
            } else {
                unlink($Entry->getPathname());
            }
        }
        rmdir($directory);
    }
}
