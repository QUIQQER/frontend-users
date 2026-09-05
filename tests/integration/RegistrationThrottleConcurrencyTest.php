<?php

namespace QUI\FrontendUsers\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\RegistrationThrottle;
use QUI\FrontendUsers\Tests\Support\DatabaseEnvironment;
use ReflectionProperty;

class RegistrationThrottleConcurrencyTest extends TestCase
{
    public static function reservations(): array
    {
        return [['new', false], ['expired', false], ['new', true], ['expired', true]];
    }

    #[DataProvider('reservations')]
    public function testConcurrentRequestsRespectTheAttemptLimit(string $state, bool $lookup): void
    {
        $dir = sys_get_temp_dir() . '/frontend-users-registration-throttle-race-' . bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $processes = [];
        $Original = QUI::getDataBaseConnection();
        $Property = new ReflectionProperty(QUI::class, 'QueryBuilder');
        $Shared = null;
        $ip = '2001:db8:' . implode(':', str_split(bin2hex(random_bytes(12)), 4));
        $subject = $lookup ? 'lookup:ip:' . bin2hex(inet_pton($ip)) : 'source:race:' . bin2hex(random_bytes(16));
        $otherSubject = 'account:race:' . bin2hex(random_bytes(16));
        $key = hash('sha256', $subject);
        $otherKey = hash('sha256', $otherSubject);
        try {
            $database = $dir . '/database.sqlite';
            $Shared = DatabaseEnvironment::createConnection($database);
            $Property->setValue(null, $Shared);
            if (!DatabaseEnvironment::usesCiDatabase()) {
                QUI\Update::importDatabase(dirname(__DIR__, 2) . '/database.xml');
            }
            if ($state === 'expired') {
                $Shared->insert(RegistrationThrottle::table(), [
                    'subject_key' => $key, 'attempts' => 2, 'expires_at' => time() - 1
                ]);
            }
            $Property->setValue(null, $Original);

            foreach ([0, 1, 2, 3] as $i) {
                $input = $dir . '/input-' . $i;
                file_put_contents($input, json_encode([
                    'database' => $database, 'ready' => $dir . '/ready-' . $i,
                    'go' => $dir . '/go', 'result' => $dir . '/result-' . $i, 'lookup' => $lookup,
                    'subject' => $subject, 'ip' => $ip
                ], JSON_THROW_ON_ERROR));
                $process = proc_open(
                    [
                    PHP_BINARY, dirname(__DIR__, 2) . '/tools/phpunit', '--no-configuration',
                    '--bootstrap', dirname(__DIR__) . '/phpunit-bootstrap.php',
                    dirname(__DIR__) . '/Fixtures/RegistrationThrottleRaceWorkerTest.php'
                    ],
                    [1 => ['file', $dir . '/output-' . $i, 'w'], 2 => ['file', $dir . '/error-' . $i, 'w']],
                    $pipes,
                    null,
                    array_replace(getenv(), ['FRONTEND_USERS_REGISTRATION_THROTTLE_RACE_INPUT' => $input])
                );
                self::assertIsResource($process);
                $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            while (count(glob($dir . '/ready-*')) < 4) {
                if (microtime(true) > $deadline) {
                    self::fail('Registration throttle workers did not reach the barrier: ' . $this->workerOutput($dir));
                }
                usleep(10000);
            }
            file_put_contents($dir . '/go', 'go');
            foreach ($processes as $process) {
                self::assertSame(0, proc_close($process), $this->workerOutput($dir));
            }
            $processes = [];
            $results = [];
            foreach ([0, 1, 2, 3] as $i) {
                $results[] = json_decode(file_get_contents($dir . '/result-' . $i), true, flags: JSON_THROW_ON_ERROR);
            }
            self::assertCount(2, array_filter($results));
            self::assertCount(1, $Shared->createQueryBuilder()->select('*')->from(RegistrationThrottle::table())
                ->where('subject_key = :key')->setParameter('key', $key)
                ->executeQuery()->fetchAllAssociative());
            self::assertSame(2, (int)$Shared->fetchOne(
                'SELECT attempts FROM ' . RegistrationThrottle::table() . ' WHERE subject_key = ?',
                [$key]
            ));
            $Property->setValue(null, $Shared);
            $Shared->transactional(static function () use ($Shared, $subject, $otherSubject, $key, $otherKey): void {
                $acquire = new \ReflectionMethod(RegistrationThrottle::class, 'acquire');
                self::assertFalse($acquire->invoke(null, $subject, 2));
                // A denied INSERT must not poison PostgreSQL's surrounding transaction.
                self::assertTrue($acquire->invoke(null, $otherSubject, 2));
                self::assertCount(2, $Shared->createQueryBuilder()->select('*')->from(RegistrationThrottle::table())
                    ->where('subject_key IN (:key, :other)')->setParameter('key', $key)->setParameter('other', $otherKey)
                    ->executeQuery()->fetchAllAssociative());
            });
        } finally {
            $Property->setValue(null, $Original);
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            if ($Shared !== null) {
                foreach ([$key, $otherKey] as $ownedKey) {
                    $Shared->delete(RegistrationThrottle::table(), ['subject_key' => $ownedKey]);
                }
                $Shared->close();
            }
            foreach (glob($dir . '/*') as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    private function workerOutput(string $dir): string
    {
        $output = '';
        foreach (array_merge(glob($dir . '/output-*'), glob($dir . '/error-*')) as $file) {
            $output .= file_get_contents($file);
        }
        return $output;
    }
}
