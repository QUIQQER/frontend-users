<?php

namespace QUI\FrontendUsers;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use QUI;
use QUI\Utils\Doctrine;

/**
 * Limits public activation mail requests independently of browser sessions.
 */
final class ActivationResend
{
    public const ACCOUNT_COOLDOWN = 300;
    public const SOURCE_COOLDOWN = 60;

    public static function table(): string
    {
        return QUI::getDBTableName('quiqqer_frontend_users_activation_resend');
    }

    public static function request(string $email): void
    {
        // Symfony only accepts forwarded addresses from configured trusted proxies.
        $source = QUI::getRequest()->getClientIp();
        $address = $source !== null && filter_var($source, FILTER_VALIDATE_IP) ? inet_pton($source) : false;

        if ($address === false) {
            return;
        }

        $Connection = QUI::getDataBaseConnection();
        $Connection->createQueryBuilder()
            ->delete(Doctrine::quoteIdentifier(self::table()))
            ->where('expires_at <= :now')
            ->setParameter('now', time())
            ->executeStatement();

        // Unknown addresses consume the source quota too, before any account lookup.
        if (!self::acquire('source:' . bin2hex($address), self::SOURCE_COOLDOWN)) {
            return;
        }

        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $User = QUI::getUsers()->getUserByMail($email);
        } catch (QUI\Users\Exception) {
            return;
        }

        if (!self::acquire('account:' . $User->getUUID(), self::ACCOUNT_COOLDOWN)) {
            return;
        }

        // Keep both reservations even on mail failure to limit SMTP retries.
        Handler::getInstance()->resendActivationMail($User);
    }

    private static function acquire(string $subject, int $cooldown): bool
    {
        $Connection = QUI::getDataBaseConnection();
        $table = Doctrine::quoteIdentifier(self::table());
        $key = hash('sha256', $subject);
        $now = time();

        if (
            $Connection->createQueryBuilder()
                ->update($table)
                ->set('expires_at', ':expires_at')
                ->where('subject_key = :subject_key')
                ->andWhere('expires_at <= :now')
                ->setParameter('expires_at', $now + $cooldown)
                ->setParameter('subject_key', $key)
                ->setParameter('now', $now)
                ->executeStatement() === 1
        ) {
            return true;
        }

        try {
            // A savepoint also keeps an outer PostgreSQL transaction usable on conflict.
            $Connection->transactional(static function () use ($Connection, $table, $key, $now, $cooldown): void {
                $Connection->insert($table, ['subject_key' => $key, 'expires_at' => $now + $cooldown]);
            });

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
