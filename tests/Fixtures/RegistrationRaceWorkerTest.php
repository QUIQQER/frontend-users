<?php

namespace QUI\FrontendUsers\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\Rest\RegistrationData;
use QUI\FrontendUsers\Rest\Routes\PostRegister;
use QUI\FrontendUsers\Tests\Support\DatabaseEnvironment;
use QUI\Permissions\Permission;
use QUI\Utils\Singleton;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Throwable;

final class RegistrationRaceWorkerTest extends TestCase
{
    public function testWorker(): void
    {
        $file = getenv('FRONTEND_USERS_RACE_INPUT');
        self::assertIsString($file);
        $input = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        QUI::getRequest()->server->set('REMOTE_ADDR', $input['ip']);
        $Connection = DatabaseEnvironment::createConnection($input['database']);
        if (!DatabaseEnvironment::usesCiDatabase()) {
            $Connection->executeStatement('PRAGMA busy_timeout = 10000');
        }
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $Connection);

        $Users = QUI::getUsers();
        foreach (['users' => [], 'usersUUIDs' => [], 'SystemUser' => null, 'Session' => null] as $key => $value) {
            (new ReflectionProperty($Users, $key))->setValue($Users, $value);
        }
        Permission::setUser($Users->getSystemUser());
        (new ReflectionProperty($Users, 'Session'))->setValue($Users, $Users->getSystemUser());
        foreach (QUI::getEvents()->getList()['onUserCreate'] ?? [] as $event) {
            if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                QUI::getEvents()->removeEvent('onUserCreate', $event['callable']);
            }
        }
        // Keep insertion and email persistence apart so the old race is reproducible.
        QUI::getEvents()->addEvent('onUserCreate', static function (): void {
            usleep(250000);
        });

        $mails = [];
        $Handler = $this->getMockBuilder(Handler::class)->onlyMethods(['sendMail'])->getMock();
        $Handler->method('sendMail')->willReturnCallback(static function (array $data, array $recipients) use (&$mails): void {
            $mails[] = $recipients;
        });
        $instances = new ReflectionProperty(Singleton::class, 'instances');
        $instances->setValue(null, array_replace($instances->getValue(), [Handler::class => $Handler]));

        $barrier = static function () use ($input): void {
            file_put_contents($input['ready'], 'ready');
            $deadline = microtime(true) + 15;
            while (!file_exists($input['go'])) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Registration barrier timed out.');
                }
                usleep(10000);
            }
        };

        try {
            if ($input['transport'] === 'browser') {
                $Registrar = $this->getMockBuilder(Registrar::class)
                    ->onlyMethods(['checkUserAttributes', 'getType'])->getMock();
                $Registrar->method('getType')->willReturn(Registrar::class);
                $Registrar->method('checkUserAttributes')->willReturnCallback($barrier);
                $_POST = $input['data'] + ['registration' => 1];
                $Control = new Registration(['Registrar' => $Registrar]);
                $Control->register();
            } else {
                $Data = $this->getMockBuilder(RegistrationData::class)->onlyMethods(['validate'])->getMock();
                $Data->setAttributes($input['data']);
                $Data->method('validate')->willReturnCallback(static function () use ($Data, $barrier): void {
                    $Validation = new RegistrationData();
                    $Validation->setAttributes($Data->getAttributes());
                    $Validation->validate();
                    $barrier();
                });
                (new ReflectionMethod(PostRegister::class, 'registerUser'))->invoke(null, $Data);
            }
            $result = ['success' => true];
        } catch (Throwable $Exception) {
            $result = ['success' => false, 'exception' => get_class($Exception), 'message' => $Exception->getMessage()];
        }
        $result['mails'] = $mails;
        file_put_contents($input['result'], json_encode($result, JSON_THROW_ON_ERROR));
        $Connection->close();
    }
}
