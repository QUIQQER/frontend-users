<?php

namespace QUI\FrontendUsers\Tests\Integration;

use DateTimeImmutable;
use QUI;
use QUI\FrontendUsers\Cleanup\Cron;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Tests\Support\CleanupTestConsole;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;

class CleanupConsoleWorkflowTest extends DatabaseTestCase
{
    private const ATTRIBUTE_PREFIX = 'frontend-cleanup-phpunit-';

    public function testExecuteRejectsMissingFilter(): void
    {
        $Console = new CleanupTestConsole();
        $Console->execute();

        self::assertStringContainsString(
            'No filter criteria for users given',
            implode("\n", $Console->output)
        );
    }

    public function testExecuteReportsNoMatchingUsers(): void
    {
        $Console = new CleanupTestConsole();
        $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'missing', uniqid('', true));
        $Console->execute();

        self::assertStringContainsString(
            'No users were found that match the given criteria.',
            implode("\n", $Console->output)
        );
    }

    public function testDryRunAppliesAllFiltersWithoutDeletingUser(): void
    {
        $User = $this->createCleanupUser([
            'regdate' => strtotime('-30 days'),
            'lastvisit' => strtotime('-20 days'),
            'active' => 0,
            'usergroup' => ',987654321,',
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'marker":"match"}'
        ]);

        $Console = new CleanupTestConsole();
        $Console->setArgument('createDateFrom', '2000-01-01');
        $Console->setArgument('createDateTo', '2100-01-01');
        $Console->setArgument('atLeastDaysOld', '10');
        $Console->setArgument('atLeastNotLoggedInForDays', '10');
        $Console->setArgument('activeStatus', '0');
        $Console->setArgument('inGroups', '987654321');
        $Console->setArgument('notInGroups', '987654322');
        $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'marker', 'match');
        $Console->execute();

        self::assertTrue(QUI::getUsers()->usernameExists($User->getUsername()));
        self::assertStringContainsString('Number of users to delete: 1', implode("\n", $Console->output));
    }

    public function testInteractiveConfirmationCanDeclineDeletion(): void
    {
        $User = $this->createCleanupUser([
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'marker":"decline"}'
        ]);

        $Console = new CleanupTestConsole();
        $Console->consoleMode = true;
        $Console->input = 'n';
        $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'marker', 'decline');
        $Console->execute();

        self::assertTrue(QUI::getUsers()->usernameExists($User->getUsername()));
        self::assertStringContainsString('Should the selected users be deleted', implode("\n", $Console->output));
    }

    public function testInteractiveConfirmationDeletesMatchingUser(): void
    {
        $User = $this->createCleanupUser([
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'marker":"confirm"}'
        ]);

        $Console = new CleanupTestConsole();
        $Console->consoleMode = true;
        $Console->input = 'Y';
        $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'marker', 'confirm');
        $Console->execute();

        self::assertFalse(QUI::getUsers()->usernameExists($User->getUsername()));
        self::assertStringContainsString('Deleted users: 1', implode("\n", $Console->output));
    }

    public function testDeleteArgumentFiresCompatibilityEventAndDeletesWithoutPrompt(): void
    {
        $User = $this->createCleanupUser([
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'marker":"delete"}'
        ]);
        $deletedUserUuid = null;
        $listener = static function (QUI\Interfaces\Users\User $DeletedUser) use (&$deletedUserUuid): void {
            $deletedUserUuid = $DeletedUser->getUUID();
        };
        $Events = QUI::getEvents();
        $Events->addEvent('onQuiqqerFrontendUsersCleanupDeleteUser', $listener);

        try {
            $Console = new CleanupTestConsole();
            $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'marker', 'delete');
            $Console->setArgument('delete', true);
            $Console->execute();
        } finally {
            $Events->removeEvent('onQuiqqerFrontendUsersCleanupDeleteUser', $listener);
        }

        self::assertFalse(QUI::getUsers()->usernameExists($User->getUsername()));
        self::assertSame($User->getUUID(), $deletedUserUuid);
        self::assertStringNotContainsString('Should the selected users be deleted', implode("\n", $Console->output));
    }

    public function testNotInGroupsExcludesUsersInEveryConfiguredGroup(): void
    {
        $Excluded = $this->createCleanupUser([
            'usergroup' => ',111,',
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'marker":"group-exclusion"}'
        ]);
        $Allowed = $this->createCleanupUser([
            'usergroup' => ',333,',
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'marker":"group-exclusion"}'
        ]);

        $Console = new CleanupTestConsole();
        $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'marker', 'group-exclusion');
        $Console->setArgument('notInGroups', '111,222');
        $Console->setArgument('delete', true);
        $Console->execute();

        self::assertTrue(QUI::getUsers()->usernameExists($Excluded->getUsername()));
        self::assertFalse(QUI::getUsers()->usernameExists($Allowed->getUsername()));
        self::assertStringContainsString('Deleted users: 1', implode("\n", $Console->output));
    }

    public function testAttributeLikeWildcardsAndSqlPayloadAreTreatedAsData(): void
    {
        $User = $this->createCleanupUser([
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'injection":"safe"}'
        ]);

        foreach (['%', 'safe" OR 1=1 --'] as $value) {
            $Console = new CleanupTestConsole();
            $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'injection', $value);
            $Console->setArgument('delete', true);
            $Console->execute();

            self::assertStringContainsString('No users were found', implode("\n", $Console->output));
        }

        self::assertTrue(QUI::getUsers()->usernameExists($User->getUsername()));
    }

    public function testGroupLikeWildcardsAreMatchedLiterally(): void
    {
        $User = $this->createCleanupUser([
            'usergroup' => ',123,',
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'marker":"group-wildcard"}'
        ]);

        $Console = new CleanupTestConsole();
        $Console->setArgument('inGroups', '%');
        $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'marker', 'group-wildcard');
        $Console->setArgument('delete', true);
        $Console->execute();

        self::assertTrue(QUI::getUsers()->usernameExists($User->getUsername()));
        self::assertStringContainsString('No users were found', implode("\n", $Console->output));
    }

    public function testCreateDateToIncludesTheEntireSpecifiedDay(): void
    {
        $User = $this->createCleanupUser([
            'regdate' => (new DateTimeImmutable('2024-03-04 15:30:00'))->getTimestamp(),
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'marker":"inclusive-end-date"}'
        ]);

        $Console = new CleanupTestConsole();
        $Console->setArgument('createDateFrom', '2024-03-04');
        $Console->setArgument('createDateTo', '2024-03-04');
        $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'marker', 'inclusive-end-date');
        $Console->setArgument('delete', true);
        $Console->execute();

        self::assertFalse(QUI::getUsers()->usernameExists($User->getUsername()));
    }

    public function testInvalidFiltersDoNotEnableDeletion(): void
    {
        foreach ([['activeStatus', 'inactive'], ['atLeastDaysOld', '-10']] as [$name, $value]) {
            $Console = new CleanupTestConsole();
            $Console->setArgument($name, $value);
            $Console->execute();

            self::assertStringContainsString(
                'No filter criteria for users given',
                implode("\n", $Console->output)
            );
        }
    }

    public function testBackendRegistrationsAreNeverDeleted(): void
    {
        $User = $this->createCleanupUser([
            'extra' => '{"quiqqer.frontendUsers.registrar":false,"' . self::ATTRIBUTE_PREFIX . 'marker":"backend"}'
        ]);

        $Console = new CleanupTestConsole();
        $Console->setArgument('attr-' . self::ATTRIBUTE_PREFIX . 'marker', 'backend');
        $Console->setArgument('delete', true);
        $Console->execute();

        self::assertTrue(QUI::getUsers()->usernameExists($User->getUsername()));
    }

    public function testCronMapsEmailVerificationFilterAndDeletesMatchingUser(): void
    {
        $User = $this->createCleanupUser([
            'extra' => '{"' . Handler::USER_ATTR_EMAIL_VERIFIED . '":false}'
        ]);

        Cron::cleanup(['emailVerified' => '0']);

        self::assertFalse(QUI::getUsers()->usernameExists($User->getUsername()));
    }

    public function testCronExecutesSafelyWhenCustomFilterHasNoMatch(): void
    {
        $User = $this->createCleanupUser([
            'extra' => '{"' . self::ATTRIBUTE_PREFIX . 'marker":"cron-safe"}'
        ]);

        Cron::cleanup([
            'attr-' . self::ATTRIBUTE_PREFIX . 'cron-missing' => uniqid('', true)
        ]);

        self::assertTrue(QUI::getUsers()->usernameExists($User->getUsername()));
    }

    /** @param array<string, int|string> $databaseValues */
    private function createCleanupUser(array $databaseValues): QUI\Users\User
    {
        $User = $this->createUser();

        self::getConnection()->update(
            QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()),
            $databaseValues,
            ['uuid' => $User->getUUID()]
        );

        return $User;
    }
}
