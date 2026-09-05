<?php

namespace QUI\FrontendUsers\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\FrontendUsers\ActivationResend;
use ReflectionProperty;

class ActivationResendConcurrencyTest extends TestCase
{
    public static function reservations(): array
    {
        return [['new'], ['expired']];
    }

    #[DataProvider('reservations')]
    public function testConcurrentRequestsAcquireOnlyOneReservation(string $state): void
    {
        $dir = sys_get_temp_dir() . '/frontend-users-resend-race-' . bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $processes = [];
        $Original = QUI::getDataBaseConnection();
        $Property = new ReflectionProperty(QUI::class, 'QueryBuilder');
        $Shared = null;
        try {
            // Optional connection must point to an isolated, disposable test database.
            $external = getenv('FRONTEND_USERS_RESEND_TEST_DATABASE');
            $connection = $external ? json_decode($external, true, flags: JSON_THROW_ON_ERROR)
                : ['driver' => 'pdo_sqlite', 'path' => $dir . '/database.sqlite'];
            $Shared = DriverManager::getConnection($connection);
            $Property->setValue(null, $Shared);
            QUI\Update::importDatabase(dirname(__DIR__, 2) . '/database.xml');
            $Shared->createQueryBuilder()->delete(ActivationResend::table())->executeStatement();
            if ($state === 'expired') {
                $Shared->insert(ActivationResend::table(), [
                    'subject_key' => hash('sha256', 'source:race'), 'expires_at' => time() - 1
                ]);
            }
            $Property->setValue(null, $Original);

            foreach ([0, 1, 2, 3] as $i) {
                $input = $dir . '/input-' . $i;
                file_put_contents($input, json_encode([
                    'connection' => $connection, 'ready' => $dir . '/ready-' . $i,
                    'go' => $dir . '/go', 'result' => $dir . '/result-' . $i
                ], JSON_THROW_ON_ERROR));
                $process = proc_open(
                    [
                    PHP_BINARY, dirname(__DIR__, 2) . '/tools/phpunit', '--no-configuration',
                    '--bootstrap', dirname(__DIR__) . '/phpunit-bootstrap.php',
                    dirname(__DIR__) . '/Fixtures/ActivationResendRaceWorkerTest.php'
                    ],
                    [1 => ['file', $dir . '/output-' . $i, 'w'], 2 => ['file', $dir . '/error-' . $i, 'w']],
                    $pipes,
                    null,
                    array_replace(getenv(), ['FRONTEND_USERS_RESEND_RACE_INPUT' => $input])
                );
                self::assertIsResource($process);
                $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            while (count(glob($dir . '/ready-*')) < 4) {
                if (microtime(true) > $deadline) {
                    self::fail('Resend workers did not reach the barrier: ' . $this->workerOutput($dir));
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
            self::assertCount(1, array_filter($results));
            self::assertCount(1, $Shared->createQueryBuilder()->select('*')->from(ActivationResend::table())
                ->executeQuery()->fetchAllAssociative());
            $Property->setValue(null, $Shared);
            $Shared->transactional(static function () use ($Shared): void {
                $acquire = new \ReflectionMethod(ActivationResend::class, 'acquire');
                self::assertFalse($acquire->invoke(null, 'source:race', 60));
                // A denied INSERT must not poison PostgreSQL's surrounding transaction.
                self::assertTrue($acquire->invoke(null, 'account:another-user', 300));
                self::assertCount(2, $Shared->createQueryBuilder()->select('*')->from(ActivationResend::table())
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
            $Shared?->close();
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
