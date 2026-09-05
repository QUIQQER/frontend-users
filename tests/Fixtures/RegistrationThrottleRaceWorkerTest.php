<?php

namespace QUI\FrontendUsers\Tests\Fixtures;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\RegistrationThrottle;
use ReflectionMethod;
use ReflectionProperty;

final class RegistrationThrottleRaceWorkerTest extends TestCase
{
    public function testWorker(): void
    {
        $input = json_decode(file_get_contents(getenv('FRONTEND_USERS_REGISTRATION_THROTTLE_RACE_INPUT')), true, flags: JSON_THROW_ON_ERROR);
        $Connection = DriverManager::getConnection($input['connection']);
        if ($Connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
            $Connection->executeStatement('PRAGMA busy_timeout = 10000');
        }
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $Connection);
        file_put_contents($input['ready'], 'ready');
        $deadline = microtime(true) + 15;
        while (!file_exists($input['go'])) {
            if (microtime(true) > $deadline) {
                self::fail('Registration throttle race barrier timed out.');
            }
            usleep(10000);
        }
        if (!empty($input['lookup'])) {
            QUI::getRequest()->server->set('REMOTE_ADDR', '192.0.2.99');
            $Handler = $this->getMockBuilder(QUI\FrontendUsers\Handler::class)->onlyMethods(['getRegistrationSettings'])->getMock();
            $Handler->method('getRegistrationSettings')->willReturn(['throttleLookupIpLimit' => 2]);
            $Instances = new ReflectionProperty(QUI\Utils\Singleton::class, 'instances');
            $Instances->setValue(null, array_replace($Instances->getValue(), [QUI\FrontendUsers\Handler::class => $Handler]));
            try {
                RegistrationThrottle::reserveLookup();
                $allowed = true;
            } catch (QUI\FrontendUsers\Exception $Exception) {
                self::assertSame(429, $Exception->getCode());
                $allowed = false;
            }
        } else {
            $allowed = (new ReflectionMethod(RegistrationThrottle::class, 'acquire'))->invoke(null, 'source:race', 2);
        }
        file_put_contents($input['result'], json_encode($allowed, JSON_THROW_ON_ERROR));
        self::assertIsBool($allowed);
        $Connection->close();
    }
}
