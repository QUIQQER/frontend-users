<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Interfaces\Users\User;

/** Binds automatic activation login to the browser that created the account. */
final class ActivationLogin
{
    public const SESSION_KEY = 'quiqqer.frontend-users.activationLogin';
    public const USER_ATTRIBUTE = 'quiqqer.frontend-users.activationLoginHash';
    public const VALID_DURATION = 86400;

    /** Called only for a newly created browser registration, never when resending mail. */
    public static function bind(User $User): void
    {
        $nonce = bin2hex(random_bytes(32));
        $User->setAttribute(self::USER_ATTRIBUTE, hash('sha256', $nonce));
        QUI::getSession()->set(self::SESSION_KEY, [
            'uuid' => $User->getUUID(),
            'nonce' => $nonce,
            'created' => time()
        ]);
    }

    public static function consume(User $User): bool
    {
        $binding = QUI::getSession()->get(self::SESSION_KEY);
        $hash = $User->getAttribute(self::USER_ATTRIBUTE);
        $now = time();

        if (
            !is_array($binding)
            || ($binding['uuid'] ?? null) !== $User->getUUID()
            || !is_string($binding['nonce'] ?? null)
            || !is_int($binding['created'] ?? null)
            || $binding['created'] > $now
            || $binding['created'] < $now - self::VALID_DURATION
            || !is_string($hash)
            || !hash_equals($hash, hash('sha256', $binding['nonce']))
        ) {
            return false;
        }

        QUI::getSession()->remove(self::SESSION_KEY);
        $User->setAttribute(self::USER_ATTRIBUTE, false);
        return true;
    }
}
