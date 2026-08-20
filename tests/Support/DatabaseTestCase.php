<?php

namespace QUI\FrontendUsers\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Permissions\Permission;
use QUI\Update;
use ReflectionProperty;
use Throwable;

abstract class DatabaseTestCase extends TestCase
{
    protected const TEST_PREFIX = 'phpunit-frontend-users-';

    private array $previousSessionValues = [];
    private array $previousRequest = [];
    private array $previousPost = [];
    private array $previousGet = [];
    private array $previousServer = [];
    private array $previousRequestBag = [];
    private array $configValues = [];
    private string $previousLocale = '';
    private Connection $originalConnection;
    private Connection $connection;
    private ?QUI\Permissions\Manager $previousPermissionManager;
    private mixed $previousPermissionUser;

    /** @var array<string, mixed> */
    private array $previousUsersState = [];

    /** @var array<string, mixed> */
    private array $previousGroupsState = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = QUI::getDataBaseConnection();
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ]);
        $this->previousPermissionManager = QUI::$Rights;
        $this->previousPermissionUser = (new ReflectionProperty(Permission::class, 'User'))->getValue();
        $this->previousUsersState = $this->getObjectState(QUI::getUsers(), [
            'multipleCallPrevention',
            'users',
            'usersUUIDs',
            'Nobody',
            'SystemUser',
            'Session'
        ]);
        $this->previousGroupsState = $this->getObjectState(QUI::getGroups(), [
            'Everyone',
            'Guest',
            'groups',
            'groupIdsToHashes',
            'data'
        ]);

        $this->setConnection($this->connection);
        $this->setObjectState(QUI::getUsers(), [
            'multipleCallPrevention' => false,
            'users' => [],
            'usersUUIDs' => [],
            'Nobody' => null,
            'SystemUser' => null,
            'Session' => null
        ]);
        $this->setObjectState(QUI::getGroups(), [
            'Everyone' => null,
            'Guest' => null,
            'groups' => [],
            'groupIdsToHashes' => [],
            'data' => []
        ]);
        QUI::$Rights = null;
        Permission::setUser(QUI::getUsers()->getSystemUser());

        Update::importDatabase(OPT_DIR . 'quiqqer/core/database.xml');
        Update::importDatabase(OPT_DIR . 'quiqqer/verification/database.xml');
        $this->connection->insert(QUI\Users\Manager::table(), [
            'id' => 5,
            'uuid' => '5',
            'username' => 'system',
            'active' => 1,
            'su' => 1
        ]);
        $this->connection->insert(QUI\Users\Manager::table(), [
            'id' => 10,
            'uuid' => 'phpunit-sqlite-sequence',
            'username' => 'phpunit-sqlite-sequence'
        ]);
        $this->connection->delete(QUI\Users\Manager::table(), ['id' => 10]);
        $this->connection->insert(QUI\Groups\Manager::table(), [
            'id' => 2,
            'uuid' => (string)QUI::conf('globals', 'root'),
            'name' => 'PHPUnit Root',
            'parent' => 0,
            'active' => 1,
            'toolbar' => ''
        ]);
        $this->createProjectFixtures();

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

        self::replaceSessionUser(QUI::getUsers()->getSystemUser());
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();

        $Session = QUI::getSession();
        $Session->start();

        foreach ($this->previousSessionValues as $key => $value) {
            $Session->set($key, $value);
        }

        QUI::getLocale()->setCurrent($this->previousLocale);
        $_REQUEST = $this->previousRequest;
        $_POST = $this->previousPost;
        $_GET = $this->previousGet;
        $_SERVER = $this->previousServer;
        QUI::getRequest()->request->replace($this->previousRequestBag);

        $this->setConnection($this->originalConnection);
        QUI::$Rights = $this->previousPermissionManager;
        (new ReflectionProperty(Permission::class, 'User'))->setValue(null, $this->previousPermissionUser);
        $this->setObjectState(QUI::getUsers(), $this->previousUsersState);
        $this->setObjectState(QUI::getGroups(), $this->previousGroupsState);
        $this->connection->close();

        parent::tearDown();
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

        $User = $Users->createChildWithAttributes($attributes, $SystemUser);

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

    private function setConnection(Connection $Connection): void
    {
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $Connection);
    }

    private function createProjectFixtures(): void
    {
        $Project = QUI::getRewrite()->getProject() ?? QUI::getProjectManager()->getStandard();

        if ($Project === null) {
            self::fail('A project is required for the SQLite fixtures.');
        }

        $siteTable = QUI::getDBTableName($Project->getName() . '_' . $Project->getLang() . '_sites');
        $siteRelationsTable = $siteTable . '_relations';
        $mediaTable = QUI::getDBTableName($Project->getName() . '_media');
        $mediaRelationsTable = $mediaTable . '_relations';

        $Sites = new Table($siteTable);
        $Sites->addColumn('id', 'bigint', ['autoincrement' => true]);
        $Sites->addColumn('name', 'string', ['length' => 200]);
        $Sites->addColumn('title', 'text', ['notnull' => false]);
        $Sites->addColumn('short', 'text', ['notnull' => false]);
        $Sites->addColumn('content', 'text', ['notnull' => false]);
        $Sites->addColumn('type', 'string', ['length' => 255, 'notnull' => false]);
        $Sites->addColumn('layout', 'string', ['length' => 255, 'notnull' => false]);
        $Sites->addColumn('active', 'smallint', ['default' => 0]);
        $Sites->addColumn('deleted', 'smallint', ['default' => 0]);
        $Sites->addColumn('deleted_at', 'datetime', ['notnull' => false]);
        $Sites->addColumn('c_date', 'datetime', ['notnull' => false]);
        $Sites->addColumn('e_date', 'datetime', ['notnull' => false]);
        $Sites->addColumn('c_user', 'string', ['length' => 50, 'notnull' => false]);
        $Sites->addColumn('e_user', 'string', ['length' => 50, 'notnull' => false]);
        $Sites->addColumn('nav_hide', 'smallint', ['default' => 0]);
        $Sites->addColumn('order_type', 'string', ['length' => 100, 'notnull' => false]);
        $Sites->addColumn('order_field', 'bigint', ['notnull' => false]);
        $Sites->addColumn('extra', 'text', ['notnull' => false]);
        $Sites->addColumn('c_user_ip', 'string', ['length' => 40, 'notnull' => false]);
        $Sites->addColumn('image_emotion', 'text', ['notnull' => false]);
        $Sites->addColumn('image_site', 'text', ['notnull' => false]);
        $Sites->addColumn('release_from', 'datetime', ['notnull' => false]);
        $Sites->addColumn('release_to', 'datetime', ['notnull' => false]);
        $Sites->addColumn('auto_release', 'smallint', ['default' => 0]);
        $Sites->setPrimaryKey(['id']);
        $this->connection->createSchemaManager()->createTable($Sites);

        $SiteRelations = new Table($siteRelationsTable);
        $SiteRelations->addColumn('parent', 'bigint', ['notnull' => false]);
        $SiteRelations->addColumn('child', 'bigint', ['notnull' => false]);
        $SiteRelations->addColumn('oparent', 'bigint', ['notnull' => false]);
        $this->connection->createSchemaManager()->createTable($SiteRelations);

        $Media = new Table($mediaTable);
        $Media->addColumn('id', 'bigint', ['autoincrement' => true]);
        $Media->addColumn('name', 'string', ['length' => 200]);
        $Media->addColumn('title', 'text', ['notnull' => false]);
        $Media->addColumn('short', 'text', ['notnull' => false]);
        $Media->addColumn('type', 'string', ['length' => 32, 'notnull' => false]);
        $Media->addColumn('active', 'smallint', ['default' => 0]);
        $Media->addColumn('deleted', 'smallint', ['default' => 0]);
        $Media->addColumn('deleted_at', 'datetime', ['notnull' => false]);
        $Media->addColumn('c_date', 'datetime', ['notnull' => false]);
        $Media->addColumn('e_date', 'datetime', ['notnull' => false]);
        $Media->addColumn('c_user', 'string', ['length' => 50, 'notnull' => false]);
        $Media->addColumn('e_user', 'string', ['length' => 50, 'notnull' => false]);
        $Media->addColumn('file', 'text', ['notnull' => false]);
        $Media->addColumn('alt', 'text', ['notnull' => false]);
        $Media->addColumn('mime_type', 'text', ['notnull' => false]);
        $Media->addColumn('image_height', 'integer', ['notnull' => false]);
        $Media->addColumn('image_width', 'integer', ['notnull' => false]);
        $Media->addColumn('image_effects', 'text', ['notnull' => false]);
        $Media->addColumn('rate_users', 'text', ['notnull' => false]);
        $Media->addColumn('rate_count', 'float', ['notnull' => false]);
        $Media->addColumn('md5hash', 'string', ['length' => 32, 'notnull' => false]);
        $Media->addColumn('sha1hash', 'string', ['length' => 40, 'notnull' => false]);
        $Media->addColumn('priority', 'integer', ['notnull' => false]);
        $Media->addColumn('order', 'string', ['length' => 32, 'notnull' => false]);
        $Media->addColumn('pathHistory', 'text', ['notnull' => false]);
        $Media->addColumn('hidden', 'smallint', ['default' => 0]);
        $Media->addColumn('pathHash', 'string', ['length' => 32]);
        $Media->addColumn('extra', 'text', ['notnull' => false]);
        $Media->addColumn('external', 'text', ['notnull' => false]);
        $Media->setPrimaryKey(['id']);
        $this->connection->createSchemaManager()->createTable($Media);

        $MediaRelations = new Table($mediaRelationsTable);
        $MediaRelations->addColumn('parent', 'bigint');
        $MediaRelations->addColumn('child', 'bigint');
        $this->connection->createSchemaManager()->createTable($MediaRelations);

        $now = date('Y-m-d H:i:s');
        $this->connection->insert($siteTable, [
            'id' => 1,
            'name' => 'PHPUnit Root',
            'title' => 'PHPUnit Root',
            'type' => 'standard',
            'active' => 1,
            'deleted' => 0,
            'c_date' => $now,
            'e_date' => $now,
            'c_user' => '5',
            'e_user' => '5',
            'nav_hide' => 0
        ]);
        $this->connection->insert($mediaTable, [
            'id' => 1,
            'name' => 'PHPUnit Root',
            'title' => 'PHPUnit Root',
            'type' => 'folder',
            'active' => 1,
            'deleted' => 0,
            'c_date' => $now,
            'e_date' => $now,
            'c_user' => '5',
            'e_user' => '5',
            'file' => '',
            'pathHash' => md5('')
        ]);
    }

    /**
     * @param list<string> $properties
     * @return array<string, mixed>
     */
    private function getObjectState(object $Object, array $properties): array
    {
        $state = [];

        foreach ($properties as $property) {
            $state[$property] = (new ReflectionProperty($Object, $property))->getValue($Object);
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function setObjectState(object $Object, array $state): void
    {
        foreach ($state as $property => $value) {
            (new ReflectionProperty($Object, $property))->setValue($Object, $value);
        }
    }
}
