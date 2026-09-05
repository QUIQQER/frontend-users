<?php

namespace QUI\FrontendUsers\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\ActivationResend;
use QUI\FrontendUsers\Tests\Support\DatabaseEnvironment;
use ReflectionMethod;
use ReflectionProperty;

final class ActivationResendRaceWorkerTest extends TestCase
{
    public function testWorker(): void
    {
        $input = json_decode(file_get_contents(getenv('FRONTEND_USERS_RESEND_RACE_INPUT')), true, flags: JSON_THROW_ON_ERROR);
        $Connection = DatabaseEnvironment::createConnection($input['database']);
        if ($Connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
            $Connection->executeStatement('PRAGMA busy_timeout = 10000');
        }
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $Connection);
        file_put_contents($input['ready'], 'ready');
        $deadline = microtime(true) + 15;
        while (!file_exists($input['go'])) {
            if (microtime(true) > $deadline) {
                self::fail('Activation resend race barrier timed out.');
            }
            usleep(10000);
        }
        $allowed = (new ReflectionMethod(ActivationResend::class, 'acquire'))->invoke(null, $input['subject'], 60);
        file_put_contents($input['result'], json_encode($allowed, JSON_THROW_ON_ERROR));
        self::assertIsBool($allowed);
        $Connection->close();
    }
}
