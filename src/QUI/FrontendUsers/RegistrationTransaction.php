<?php

namespace QUI\FrontendUsers;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use QUI;
use QUI\Utils\Doctrine;

/**
 * Serializes this package's registrations using the shared database connection.
 */
final class RegistrationTransaction
{
    public static function table(): string
    {
        return QUI::getDBTableName('quiqqer_frontend_users_registration_lock');
    }

    /** Seed the permanent mutex row during package setup. */
    public static function setup(): void
    {
        $Connection = QUI::getDataBaseConnection();
        $table = Doctrine::quoteIdentifier(self::table());

        if ($Connection->fetchOne('SELECT id FROM ' . $table . ' WHERE id = 1') !== false) {
            return;
        }

        try {
            $Connection->insert($table, ['id' => 1, 'revision' => 0]);
        } catch (UniqueConstraintViolationException) {
            // Another setup process has already created the same mutex row.
        }
    }

    /**
     * @template T
     * @param callable(): T $register
     * @return T
     */
    public static function run(string $username, string $email, callable $register): mixed
    {
        $Connection = QUI::getDataBaseConnection();
        $hasOuterTransaction = $Connection->isTransactionActive();

        return $Connection->transactional(static function () use (
            $Connection,
            $username,
            $email,
            $register,
            $hasOuterTransaction
        ) {
            // A real UPDATE locks on MySQL/PostgreSQL and obtains SQLite's write lock.
            // One shared row preserves the database's existing collation rules.
            $updated = $Connection->executeStatement(
                'UPDATE ' . Doctrine::quoteIdentifier(self::table()) . ' SET revision = 1 - revision WHERE id = 1'
            );

            if ($updated !== 1) {
                throw new QUI\Exception('The frontend users registration lock is missing. Run package setup.');
            }

            self::assertAvailable('username', $username, $hasOuterTransaction);
            self::assertAvailable('email', trim($email), $hasOuterTransaction);

            $Session = QUI::getSession()->getSymfonySession();
            $sessionValues = $Session !== false ? $Session->all() : [];

            try {
                return $register();
            } catch (\Throwable $Exception) {
                if ($Session !== false) {
                    $userChanged = $Session->get('uid') !== ($sessionValues['uid'] ?? null);
                    $Session->replace($sessionValues);

                    if ($userChanged) {
                        QUI::getUsers()->resetSessionUser();
                    }
                }

                // Core keeps user objects and numeric-ID mappings in memory. Remove the
                // new identity before rollback so an ID reused on retry cannot resolve
                // to the rolled-back user. onDeleteUser only evicts those cache entries.
                try {
                    $uuids = $Connection->fetchFirstColumn(
                        'SELECT uuid FROM ' . Doctrine::quoteIdentifier(QUI\Users\Manager::table())
                        . ' WHERE username = :username',
                        ['username' => $username]
                    );

                    foreach ($uuids as $uuid) {
                        QUI::getUsers()->onDeleteUser(QUI::getUsers()->get($uuid));
                    }
                } catch (\Throwable $CleanupException) {
                    QUI\System\Log::writeException($CleanupException);
                }

                throw $Exception;
            }
        });
    }

    /** The column is chosen exclusively by run(), never by request input. */
    private static function assertAvailable(string $column, string $value, bool $hasOuterTransaction): void
    {
        if ($value === '') {
            return;
        }

        $Connection = QUI::getDataBaseConnection();
        $Query = $Connection->createQueryBuilder()
            ->select('uuid')
            ->from(Doctrine::quoteIdentifier(QUI\Users\Manager::table()))
            ->where(Doctrine::quoteIdentifier($column) . ' = :identity')
            ->setParameter('identity', $value)
            ->setMaxResults(1);

        if ($hasOuterTransaction && !$Connection->getDatabasePlatform() instanceof SQLitePlatform) {
            // Current read, including when a caller already has a MySQL transaction snapshot.
            $Query->forUpdate();
        }

        if ($Query->executeQuery()->fetchOne() !== false) {
            throw new Exception([
                'quiqqer/frontend-users',
                'exception.registrars.email.' . $column . '_already_exists'
            ]);
        }
    }
}
