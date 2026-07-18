<?php

namespace QUI\FrontendUsers\Tests\Support;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Verification\VerificationRepository;
use ReflectionProperty;
use Throwable;

abstract class DatabaseTestCase extends TestCase
{
    protected const TEST_PREFIX = 'phpunit-frontend-users-';

    private mixed $previousSessionUser = null;
    private array $previousSessionValues = [];
    private array $previousRequest = [];
    private array $previousPost = [];
    private array $previousGet = [];
    private array $previousServer = [];
    private array $previousRequestBag = [];
    private array $configValues = [];
    private array $trackedUserUuids = [];
    private string $previousLocale = '';

    public static function setUpBeforeClass(): void
    {
        self::skipIfDatabaseIsUnavailable();
        self::cleanupFixtures();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::skipIfDatabaseIsUnavailable();

        $this->previousRequest = $_REQUEST;
        $this->previousPost = $_POST;
        $this->previousGet = $_GET;
        $this->previousServer = $_SERVER;
        $this->previousRequestBag = QUI::getRequest()->request->all();
        $this->previousLocale = QUI::getLocale()->getCurrent();

        $Session = QUI::getSession();

        foreach (['uid', 'username', 'inAuthentication', 'auth', 'auth-primary', 'auth-secondary', 'secHash'] as $key) {
            $this->previousSessionValues[$key] = $Session->get($key);
        }

        $this->previousSessionUser = self::replaceSessionUser(QUI::getUsers()->getSystemUser());
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();

        foreach ($this->trackedUserUuids as $uuid) {
            try {
                QUI::getUsers()->deleteUser($uuid);
            } catch (Throwable) {
            }
        }

        self::cleanupFixtures();

        $Session = QUI::getSession();
        $Session->start();

        foreach ($this->previousSessionValues as $key => $value) {
            $Session->set($key, $value);
        }

        self::replaceSessionUser($this->previousSessionUser);
        QUI::getLocale()->setCurrent($this->previousLocale);
        $_REQUEST = $this->previousRequest;
        $_POST = $this->previousPost;
        $_GET = $this->previousGet;
        $_SERVER = $this->previousServer;
        QUI::getRequest()->request->replace($this->previousRequestBag);

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        self::cleanupFixtures();
    }

    protected function setPackageConfig(string $section, string $key, mixed $value): void
    {
        $Config = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $index = $section . '.' . $key;

        if (!array_key_exists($index, $this->configValues)) {
            $this->configValues[$index] = [$section, $key, $Config->get($section, $key)];
        }

        $Config->setValue($section, $key, $value);
        $Config->save();
    }

    protected function createUser(bool $active = false, array $attributes = []): QUI\Users\User
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $suffix = bin2hex(random_bytes(6));
        $username = self::TEST_PREFIX . $suffix;
        $attributes = array_merge([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Frontend',
            'lastname' => 'Users Test',
            'lang' => 'de'
        ], $attributes);

        try {
            $User = $Users->createChildWithAttributes($attributes, $SystemUser);
        } catch (Throwable $Exception) {
            self::markTestSkipped('No usable super-user fixture is available: ' . $Exception->getMessage());
        }

        $this->trackUser($User);

        if ($active) {
            $User->setPassword('phpunit-frontend-users-password', $SystemUser);
            $User->activate('', $SystemUser);
        }

        return $User;
    }

    protected function createGroup(): QUI\Groups\Group
    {
        $Groups = QUI::getGroups();
        $RootGroup = $Groups->get(QUI::conf('globals', 'root'));

        return $RootGroup->createChild(
            self::TEST_PREFIX . bin2hex(random_bytes(6)),
            QUI::getUsers()->getSystemUser()
        );
    }

    protected function trackUser(UserInterface $User): void
    {
        $this->trackedUserUuids[] = $User->getUUID();
    }

    protected static function getConnection(): Connection
    {
        return QUI::getDataBaseConnection();
    }

    protected static function replaceSessionUser(mixed $User): mixed
    {
        $Users = QUI::getUsers();
        $Property = new ReflectionProperty($Users, 'Session');
        $previousUser = $Property->getValue($Users);
        $Property->setValue($Users, $User);

        return $previousUser;
    }

    private function restoreConfig(): void
    {
        if ($this->configValues === []) {
            return;
        }

        try {
            $Config = QUI::getPackage('quiqqer/frontend-users')->getConfig();

            foreach ($this->configValues as [$section, $key, $value]) {
                $Config->setValue($section, $key, $value);
            }

            $Config->save();
        } catch (Throwable) {
        }
    }

    private static function skipIfDatabaseIsUnavailable(): void
    {
        try {
            self::getConnection()->createQueryBuilder()
                ->select('1')
                ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()))
                ->setMaxResults(1)
                ->executeQuery()
                ->free();
        } catch (Throwable $Exception) {
            self::markTestSkipped('QUIQQER database is not available: ' . $Exception->getMessage());
        }
    }

    private static function cleanupFixtures(): void
    {
        try {
            $Connection = self::getConnection();
            $usersTable = QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table());
            $rows = $Connection->createQueryBuilder()
                ->select('id', 'uuid')
                ->from($usersTable)
                ->where('username LIKE :username')
                ->setParameter('username', self::TEST_PREFIX . '%')
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                try {
                    $Connection->delete(
                        QUI::getDBTableName(VerificationRepository::TBL_VERIFICATION_PROCESSES),
                        ['identifier' => 'activate-' . $row['uuid']]
                    );
                } catch (Throwable) {
                }

                try {
                    QUI::getUsers()->deleteUser((string)$row['uuid']);
                } catch (Throwable) {
                    $Connection->delete(
                        QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::tableAddress()),
                        ['userUuid' => $row['uuid']]
                    );
                    $Connection->delete($usersTable, ['uuid' => $row['uuid']]);
                }
            }

            $groupsTable = QUI\Utils\Doctrine::quoteIdentifier(QUI\Groups\Manager::table());
            $Connection->createQueryBuilder()
                ->delete($groupsTable)
                ->where('name LIKE :name')
                ->setParameter('name', self::TEST_PREFIX . '%')
                ->executeStatement();
        } catch (Throwable) {
            // Availability is reported by the setup check; cleanup must not hide the test result.
        }
    }
}
