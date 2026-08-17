<?php

namespace QUI\FrontendUsers\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CleanupConsoleExitTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function exitStatusProvider(): iterable
    {
        yield 'success' => ['success', 0];
        yield 'failure' => ['failure', 1];
    }

    #[DataProvider('exitStatusProvider')]
    public function testConsoleModeUsesExpectedProcessExitStatus(string $mode, int $expectedStatus): void
    {
        $source = realpath(__DIR__ . '/../../src/QUI/FrontendUsers/Cleanup/Console.php');

        if ($source === false) {
            self::fail('Console source file is unavailable.');
        }

        $pipes = [];
        $Process = proc_open([
            PHP_BINARY,
            __DIR__ . '/../Fixtures/cleanup-console-exit-runner',
            $source,
            $mode
        ], [], $pipes);

        if (!is_resource($Process)) {
            self::fail('Unable to start console exit test process.');
        }

        self::assertSame($expectedStatus, proc_close($Process));
    }
}
