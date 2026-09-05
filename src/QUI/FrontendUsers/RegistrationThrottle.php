<?php

namespace QUI\FrontendUsers;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use QUI;
use QUI\Utils\Doctrine;

/**
 * Persistent attempt budgets shared by public browser and REST registrations.
 */
final class RegistrationThrottle
{
    public const WINDOW_SECONDS = 900;

    public static function table(): string
    {
        return QUI::getDBTableName('quiqqer_frontend_users_registration_throttle');
    }

    /** Reserve before validation and before the registration transaction starts. */
    public static function reserve(mixed $email, mixed $username): void
    {
        $source = QUI::getRequest()->getClientIp();
        $address = $source !== null && filter_var($source, FILTER_VALIDATE_IP) ? inet_pton($source) : false;

        if ($address === false) {
            self::deny();
        }

        // IPv4-mapped IPv6 and ordinary IPv4 represent the same source.
        if (strlen($address) === 16 && substr($address, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $address = substr($address, 12);
        }

        $settings = Handler::getInstance()->getRegistrationSettings();
        $ipLimit = self::positiveInt($settings['throttleIpLimit'] ?? null, 20);
        $identityLimit = self::positiveInt($settings['throttleIdentityLimit'] ?? null, 5);

        $Connection = QUI::getDataBaseConnection();
        $Connection->createQueryBuilder()
            ->delete(Doctrine::quoteIdentifier(self::table()))
            ->where('expires_at <= :now')
            ->setParameter('now', time())
            ->executeStatement();

        // Rejected identities consume the source quota, but rejected sources create no identity rows.
        if (!self::acquire('ip:' . bin2hex($address), $ipLimit)) {
            self::deny();
        }

        foreach (['email' => $email, 'username' => $username] as $kind => $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $identity = mb_strtolower(trim($value), 'UTF-8');

            if (!self::acquire($kind . ':' . $identity, $identityLimit)) {
                self::deny();
            }
        }
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        return $number === false ? $default : $number;
    }

    private static function deny(): never
    {
        throw new Exception(['quiqqer/frontend-users', 'exception.registration.throttled']);
    }

    private static function acquire(string $subject, int $limit): bool
    {
        $Connection = QUI::getDataBaseConnection();
        $table = Doctrine::quoteIdentifier(self::table());
        $key = hash('sha256', $subject);
        $now = time();
        $Update = $Connection->createQueryBuilder()
            ->update($table)
            ->set('attempts', 'CASE WHEN expires_at <= :now THEN 1 ELSE attempts + 1 END')
            ->set('expires_at', 'CASE WHEN expires_at <= :now THEN :expires_at ELSE expires_at END')
            ->where('subject_key = :subject_key')
            ->andWhere('(expires_at <= :now OR attempts < :attempt_limit)')
            ->setParameter('now', $now)
            ->setParameter('expires_at', $now + self::WINDOW_SECONDS)
            ->setParameter('subject_key', $key)
            ->setParameter('attempt_limit', $limit);

        if ((int)$Update->executeStatement() === 1) {
            return true;
        }

        try {
            // The savepoint keeps a caller's PostgreSQL transaction usable on INSERT conflict.
            $Connection->transactional(static function () use ($Connection, $table, $key, $now): void {
                $Connection->insert($table, [
                    'subject_key' => $key, 'attempts' => 1, 'expires_at' => $now + self::WINDOW_SECONDS
                ]);
            });
            return true;
        } catch (UniqueConstraintViolationException) {
            // Another request may have inserted the first attempt. Reserve remaining capacity atomically.
            return (int)(clone $Update)->executeStatement() === 1;
        }
    }
}
