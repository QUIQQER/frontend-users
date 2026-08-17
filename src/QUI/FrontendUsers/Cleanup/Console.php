<?php

namespace QUI\FrontendUsers\Cleanup;

use DateTimeImmutable;
use Exception;
use League\CLImate\CLImate;
use QUI;

/**
 * Delete frontend user accounts matching configurable filter criteria.
 */
class Console extends QUI\System\Console\Tool
{
    public function __construct()
    {
        $this->setName('frontend-users:cleanup')
            ->setDescription(
                'Cleanup tool for users -> Delete user accounts that meet certain criteria'
            );

        $this->addArgument(
            'createDateFrom',
            'Delete users created as of and including the specified date. [YYYY-MM-DD]',
            false,
            true
        );

        $this->addArgument(
            'createDateTo',
            'Delete users created up to and including the specified date. [YYYY-MM-DD]',
            false,
            true
        );

        $this->addArgument(
            'atLeastDaysOld',
            'Delete users older than X days. [X = positive integer]',
            false,
            true
        );

        $this->addArgument(
            'atLeastNotLoggedInForDays',
            'Delete users whose last login was X or more days ago. [X = positive integer]',
            false,
            true
        );

        $this->addArgument(
            'activeStatus',
            'Delete users whose active status equals X. [X = -1,0,1]',
            false,
            true
        );

        $this->addArgument(
            'inGroups',
            'Delete users who are in the given groups (comma-separated group IDs)',
            false,
            true
        );

        $this->addArgument(
            'notInGroups',
            'Delete users who are NOT in the given groups (comma-separated group IDs)',
            false,
            true
        );

        $this->addArgument(
            'delete',
            'Actually delete the users that are selected via the given filters',
            false,
            true
        );
    }

    /**
     * Execute the console tool.
     */
    public function execute(): void
    {
        QUI\Permissions\Permission::isAdmin();

        if (!defined('ADMIN')) {
            define('ADMIN', 1);
        }

        if (!defined('SYSTEM_INTERN')) {
            define('SYSTEM_INTERN', 1);
        }

        $QueryBuilder = QUI::getQueryBuilder()
            ->select(
                QUI\Utils\Doctrine::quoteIdentifier('id'),
                QUI\Utils\Doctrine::quoteIdentifier('username')
            )
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName('users')));
        $hasUserFilter = false;

        $createDateFrom = $this->getCreateDateFrom();

        if (!empty($createDateFrom)) {
            $QueryBuilder->andWhere(
                QUI\Utils\Doctrine::quoteIdentifier('regdate') . ' >= :createDateFrom'
            )->setParameter('createDateFrom', $createDateFrom);
            $hasUserFilter = true;
        }

        $createDateTo = $this->getCreateDateTo();

        if (!empty($createDateTo)) {
            $QueryBuilder->andWhere(
                QUI\Utils\Doctrine::quoteIdentifier('regdate') . ' < :createDateTo'
            )->setParameter('createDateTo', $createDateTo);
            $hasUserFilter = true;
        }

        $atLeastDaysOld = $this->getAtLeastDaysOld();

        if (!empty($atLeastDaysOld)) {
            $QueryBuilder->andWhere(
                QUI\Utils\Doctrine::quoteIdentifier('regdate') . ' <= :atLeastDaysOld'
            )->setParameter('atLeastDaysOld', $atLeastDaysOld);
            $hasUserFilter = true;
        }

        $atLeastNotLoggedInForDays = $this->getAtLeastNotLoggedInForDays();

        if (!empty($atLeastNotLoggedInForDays)) {
            $QueryBuilder->andWhere(
                QUI\Utils\Doctrine::quoteIdentifier('lastvisit') . ' <= :atLeastNotLoggedInForDays'
            )->setParameter('atLeastNotLoggedInForDays', $atLeastNotLoggedInForDays);
            $hasUserFilter = true;
        }

        $activeStatus = $this->getActiveStatus();

        if ($activeStatus !== false) {
            $QueryBuilder->andWhere(
                QUI\Utils\Doctrine::quoteIdentifier('active') . ' = :activeStatus'
            )->setParameter('activeStatus', $activeStatus);
            $hasUserFilter = true;
        }

        $inGroups = $this->getInGroups();

        if (!empty($inGroups)) {
            $groupConditions = [];

            foreach ($inGroups as $index => $groupId) {
                $parameter = 'inGroup' . $index;
                $groupConditions[] = QUI\Utils\Doctrine::quoteIdentifier('usergroup')
                    . " LIKE :$parameter ESCAPE '!'";
                $QueryBuilder->setParameter(
                    $parameter,
                    '%,' . self::escapeLikeValue((string)$groupId) . ',%'
                );
            }

            $QueryBuilder->andWhere($QueryBuilder->expr()->or(...$groupConditions));
            $hasUserFilter = true;
        }

        $notInGroups = $this->getNotInGroups();

        if (!empty($notInGroups)) {
            $groupConditions = [];

            foreach ($notInGroups as $index => $groupId) {
                $parameter = 'notInGroup' . $index;
                $groupConditions[] = QUI\Utils\Doctrine::quoteIdentifier('usergroup')
                    . " NOT LIKE :$parameter ESCAPE '!'";
                $QueryBuilder->setParameter(
                    $parameter,
                    '%,' . self::escapeLikeValue((string)$groupId) . ',%'
                );
            }

            $QueryBuilder->andWhere($QueryBuilder->expr()->and(...$groupConditions));
            $hasUserFilter = true;
        }

        $attributeIndex = 0;

        foreach ($this->params as $key => $value) {
            $key = str_replace('--', '', $key);

            // A CLI option without a value is boolean true and cannot be an attribute filter.
            if ($this->inConsole() && $value === true) {
                continue;
            }

            if (mb_strpos($key, 'attr-') !== 0) {
                continue;
            }

            $attribute = mb_substr($key, 5);

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $attributeParameter = 'attribute' . $attributeIndex;
            $quotedAttributeParameter = 'quotedAttribute' . $attributeIndex;
            $escapedAttribute = self::escapeLikeValue($attribute);
            $escapedValue = self::escapeLikeValue((string)$value);
            $extraColumn = QUI\Utils\Doctrine::quoteIdentifier('extra');

            $QueryBuilder->andWhere(
                $QueryBuilder->expr()->or(
                    $extraColumn . " LIKE :$attributeParameter ESCAPE '!'",
                    $extraColumn . " LIKE :$quotedAttributeParameter ESCAPE '!'"
                )
            )
                ->setParameter($attributeParameter, '%"' . $escapedAttribute . '":' . $escapedValue . '%')
                ->setParameter($quotedAttributeParameter, '%"' . $escapedAttribute . '":"' . $escapedValue . '"%');

            $attributeIndex++;
            $hasUserFilter = true;
        }

        if (!$hasUserFilter) {
            $this->exitFail('No filter criteria for users given. Please specify at least one filter criterion');
            return;
        }

        // Superusers can never be deleted by this tool.
        $QueryBuilder->andWhere(
            QUI\Utils\Doctrine::quoteIdentifier('su') . ' != :superUser'
        )->setParameter('superUser', 1);

        // Only delete users that registered via the frontend.
        $QueryBuilder->andWhere(
            QUI\Utils\Doctrine::quoteIdentifier('extra') . ' NOT LIKE :nonFrontendRegistration'
        )->setParameter(
            'nonFrontendRegistration',
            '%"quiqqer.frontendUsers.registrar":false%'
        );

        try {
            $result = $QueryBuilder->executeQuery()->fetchAllAssociative();
        } catch (Exception $Exception) {
            $this->exitFail($Exception->getMessage());
            return;
        }

        $this->writeLn("\n");

        if (empty($result)) {
            $this->writeLn('No users were found that match the given criteria.');
            $this->writeLn("\n");
            $this->exitSuccess();
            return;
        }

        $delete = $this->getArgument('delete') === true;

        $this->writeLn('Number of users to delete: ' . count($result) . "\n");

        if ($this->inConsole()) {
            $Climate = new CLImate();
            $Climate->table($result);

            if (empty($delete)) {
                $this->writeLn('Should the selected users be deleted from the QUIQQER system? [y/N]: ');
                $deleteInput = $this->readInput();

                if (mb_strtolower($deleteInput) === 'y') {
                    $delete = true;
                }
            }
        }

        if ($delete === true) {
            $Users = QUI::getUsers();
            $Events = QUI::getEvents();
            $deletedCounter = 0;

            $this->writeLn("Deleting users...\n");

            foreach ($result as $row) {
                $uid = $row['id'];
                $this->writeLn('Delete user #' . $uid . '...');

                try {
                    $User = $Users->get($uid);

                    $Events->fireEvent('quiqqerFrontendUsersCleanupDeleteUser', [$User]);
                    $User->delete();

                    $deletedCounter++;
                    $this->write(' OK!');
                } catch (Exception $Exception) {
                    $this->write(' ERROR: ' . $Exception->getMessage());
                }
            }

            $this->writeLn("\nDeleted users: " . $deletedCounter . "\n");
        }

        $this->writeLn("\n");
        $this->exitSuccess();
    }

    /**
     * @return false|int False if not configured; timestamp otherwise
     */
    protected function getCreateDateFrom(): int | false
    {
        $date = $this->getArgument('createDateFrom');

        if (!is_string($date)) {
            return false;
        }

        return self::parseDate($date)?->getTimestamp() ?? false;
    }

    /**
     * @return false|int False if not configured; exclusive timestamp otherwise
     */
    protected function getCreateDateTo(): int | false
    {
        $date = $this->getArgument('createDateTo');

        if (!is_string($date)) {
            return false;
        }

        return self::parseDate($date)?->modify('+1 day')->getTimestamp() ?? false;
    }

    /**
     * @return false|int False if not configured; maximum registration timestamp otherwise
     */
    protected function getAtLeastDaysOld(): int | false
    {
        $days = $this->getArgument('atLeastDaysOld');

        if (!is_string($days)) {
            return false;
        }

        $days = filter_var($days, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        return $days === false ? false : strtotime('-' . $days . ' days');
    }

    /**
     * @return false|int False if not configured; maximum login timestamp otherwise
     */
    protected function getAtLeastNotLoggedInForDays(): int | false
    {
        $days = $this->getArgument('atLeastNotLoggedInForDays');

        if (!is_string($days)) {
            return false;
        }

        $days = filter_var($days, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        return $days === false ? false : strtotime('-' . $days . ' days');
    }

    /**
     * @return false|int False if not configured; allowed active status otherwise
     */
    protected function getActiveStatus(): int | false
    {
        $activeStatus = $this->getArgument('activeStatus');

        if (!in_array($activeStatus, ['-1', '0', '1'], true)) {
            return false;
        }

        return (int)$activeStatus;
    }

    /** @return array<int|string> */
    protected function getInGroups(): array
    {
        $groupIds = $this->getArgument('inGroups');

        if (empty($groupIds)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $groupIds)),
            static fn(string $groupId): bool => $groupId !== ''
        ));
    }

    /** @return array<int|string> */
    protected function getNotInGroups(): array
    {
        $groupIds = $this->getArgument('notInGroups');

        if (empty($groupIds)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $groupIds)),
            static fn(string $groupId): bool => $groupId !== ''
        ));
    }

    private static function parseDate(string $date): ?DateTimeImmutable
    {
        $Date = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($Date === false || $Date->format('Y-m-d') !== $date) {
            return null;
        }

        return $Date;
    }

    private static function escapeLikeValue(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    protected function exitSuccess(): void
    {
        $this->writeLn('Konsolen-Tool Ausführung erfolgreich abgeschlossen.');
        $this->writeLn();

        if ($this->inConsole()) {
            exit(0);
        }
    }

    protected function exitFail(string $msg): void
    {
        $this->writeLn('Skript-Abbruch wegen Fehler:');
        $this->writeLn();
        $this->writeLn($msg);
        $this->writeLn();
        $this->writeLn();

        if ($this->inConsole()) {
            exit(1);
        }
    }

    protected function inConsole(): bool
    {
        return defined('QUIQQER_CONSOLE') && QUIQQER_CONSOLE;
    }
}
