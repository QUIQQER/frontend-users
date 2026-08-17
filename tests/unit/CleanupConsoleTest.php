<?php

namespace QUI\FrontendUsers\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\FrontendUsers\Tests\Support\CleanupTestConsole;

class CleanupConsoleTest extends TestCase
{
    public function testPackageRegistersAndReplacesCleanupExtension(): void
    {
        $packageDir = dirname(__DIR__, 2);
        $ConsoleXml = simplexml_load_file($packageDir . '/console.xml');
        $CronXml = simplexml_load_file($packageDir . '/cron.xml');
        $composer = json_decode((string)file_get_contents($packageDir . '/composer.json'), true);

        self::assertNotFalse($ConsoleXml);
        self::assertNotFalse($CronXml);
        self::assertIsArray($composer);

        $consoleTools = [];

        foreach ($ConsoleXml->tool as $Tool) {
            $consoleTools[] = (string)$Tool['exec'];
        }

        $cronTools = [];

        foreach ($CronXml->cron as $Cron) {
            $cronTools[] = (string)$Cron['exec'];
        }

        self::assertContains('\\QUI\\FrontendUsers\\Cleanup\\Console', $consoleTools);
        self::assertContains('\\QUI\\FrontendUsers\\Cleanup\\Cron::cleanup', $cronTools);
        self::assertSame('*', $composer['replace']['quiqqer/frontend-users-cleanup'] ?? null);
    }

    public function testConstructorConfiguresCommandAndArguments(): void
    {
        $Console = new CleanupTestConsole();

        self::assertSame('frontend-users:cleanup', $Console->getName());
        self::assertNotEmpty($Console->getDescription());
        self::assertSame([
            'createDateFrom',
            'createDateTo',
            'atLeastDaysOld',
            'atLeastNotLoggedInForDays',
            'activeStatus',
            'inGroups',
            'notInGroups',
            'delete'
        ], $Console->argumentNames());
    }

    public function testDateArgumentsAreConvertedToTimestamps(): void
    {
        $Console = new CleanupTestConsole();
        self::assertFalse($Console->createDateFrom());
        self::assertFalse($Console->createDateTo());

        $Console->setArgument('createDateFrom', '2024-01-02');
        $Console->setArgument('createDateTo', '2024-03-04');

        self::assertSame((new DateTimeImmutable('2024-01-02'))->getTimestamp(), $Console->createDateFrom());
        self::assertSame((new DateTimeImmutable('2024-03-05'))->getTimestamp(), $Console->createDateTo());
    }

    public function testDateArgumentsRejectInvalidOrUnexpectedFormats(): void
    {
        $Console = new CleanupTestConsole();
        $Console->setArgument('createDateFrom', '2024-02-30');
        $Console->setArgument('createDateTo', 'March 4, 2024');

        self::assertFalse($Console->createDateFrom());
        self::assertFalse($Console->createDateTo());
    }

    public function testAgeArgumentsRejectEmptyAndInvalidValues(): void
    {
        $Console = new CleanupTestConsole();
        self::assertFalse($Console->atLeastDaysOld());
        self::assertFalse($Console->atLeastNotLoggedInForDays());

        $Console->setArgument('atLeastDaysOld', 'invalid');
        $Console->setArgument('atLeastNotLoggedInForDays', '-2');
        self::assertFalse($Console->atLeastDaysOld());
        self::assertFalse($Console->atLeastNotLoggedInForDays());

        $Console->setArgument('atLeastDaysOld', '1.5');
        $Console->setArgument('atLeastNotLoggedInForDays', '0');
        self::assertFalse($Console->atLeastDaysOld());
        self::assertFalse($Console->atLeastNotLoggedInForDays());
    }

    public function testAgeArgumentsReturnExpectedTimestamps(): void
    {
        $Console = new CleanupTestConsole();
        $Console->setArgument('atLeastDaysOld', '10');
        $Console->setArgument('atLeastNotLoggedInForDays', '20');

        self::assertEqualsWithDelta(strtotime('-10 days'), $Console->atLeastDaysOld(), 1);
        self::assertEqualsWithDelta(strtotime('-20 days'), $Console->atLeastNotLoggedInForDays(), 1);
    }

    /** @return iterable<string, array{bool|string, false|int}> */
    public static function activeStatusProvider(): iterable
    {
        yield 'missing' => [false, false];
        yield 'minus one' => ['-1', -1];
        yield 'inactive' => ['0', 0];
        yield 'active' => ['1', 1];
        yield 'invalid' => ['2', false];
        yield 'non-numeric' => ['inactive', false];
    }

    #[DataProvider('activeStatusProvider')]
    public function testActiveStatusOnlyAcceptsDocumentedValues(bool|string $value, int|false $expected): void
    {
        $Console = new CleanupTestConsole();

        if ($value !== false) {
            $Console->setArgument('activeStatus', $value);
        }

        self::assertSame($expected, $Console->activeStatus());
    }

    public function testGroupArgumentsAreParsed(): void
    {
        $Console = new CleanupTestConsole();
        self::assertSame([], $Console->inGroups());
        self::assertSame([], $Console->notInGroups());

        $Console->setArgument('inGroups', ' 12,34, ');
        $Console->setArgument('notInGroups', '56,, 78');
        self::assertSame(['12', '34'], $Console->inGroups());
        self::assertSame(['56', '78'], $Console->notInGroups());
    }

    public function testExitMessagesAreWritten(): void
    {
        $Console = new CleanupTestConsole();
        $Console->callExitSuccess();
        $Console->callExitFail('fixture failure');

        $output = implode("\n", $Console->output);
        self::assertStringContainsString('erfolgreich abgeschlossen', $output);
        self::assertStringContainsString('Skript-Abbruch wegen Fehler', $output);
        self::assertStringContainsString('fixture failure', $output);
    }

    public function testConsoleModeCanBeControlledByTestDouble(): void
    {
        $Console = new CleanupTestConsole();
        self::assertFalse($Console->isConsoleMode());

        $Console->consoleMode = true;
        self::assertTrue($Console->isConsoleMode());
    }
}
