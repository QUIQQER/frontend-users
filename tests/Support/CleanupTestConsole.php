<?php

namespace QUI\FrontendUsers\Tests\Support;

use QUI\FrontendUsers\Cleanup\Console;

class CleanupTestConsole extends Console
{
    /** @var list<string> */
    public array $output = [];
    public bool $consoleMode = false;
    public string $input = 'n';

    public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
    {
        $this->output[] = $msg;
    }

    public function write(string $msg, bool|string $color = false, bool|string $bg = false): void
    {
        $this->output[] = $msg;
    }

    public function readInput(): string
    {
        return $this->input;
    }

    public function createDateFrom(): int | false
    {
        return $this->getCreateDateFrom();
    }

    public function createDateTo(): int | false
    {
        return $this->getCreateDateTo();
    }

    public function atLeastDaysOld(): int | false
    {
        return $this->getAtLeastDaysOld();
    }

    public function atLeastNotLoggedInForDays(): int | false
    {
        return $this->getAtLeastNotLoggedInForDays();
    }

    public function activeStatus(): int | false
    {
        return $this->getActiveStatus();
    }

    /** @return array<int|string> */
    public function inGroups(): array
    {
        return $this->getInGroups();
    }

    /** @return array<int|string> */
    public function notInGroups(): array
    {
        return $this->getNotInGroups();
    }

    /** @return list<string> */
    public function argumentNames(): array
    {
        return array_keys($this->paramsList);
    }

    public function callExitSuccess(): void
    {
        $this->exitSuccess();
    }

    public function callExitFail(string $message): void
    {
        $this->exitFail($message);
    }

    public function isConsoleMode(): bool
    {
        return $this->inConsole();
    }

    protected function exitSuccess(): void
    {
        $consoleMode = $this->consoleMode;
        $this->consoleMode = false;
        parent::exitSuccess();
        $this->consoleMode = $consoleMode;
    }

    protected function exitFail(string $msg): void
    {
        $consoleMode = $this->consoleMode;
        $this->consoleMode = false;
        parent::exitFail($msg);
        $this->consoleMode = $consoleMode;
    }

    protected function inConsole(): bool
    {
        return $this->consoleMode;
    }
}
