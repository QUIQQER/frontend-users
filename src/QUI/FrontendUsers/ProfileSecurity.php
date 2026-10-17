<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Interfaces\Users\User;
use QUI\Security\CsrfToken;

/**
 * Request protection for the browser profile, independent of optional CSRF packages.
 */
final class ProfileSecurity
{
    public const RECENT_AUTH_SESSION_KEY = 'quiqqer.frontend-users.recentAuth';
    public const RECENT_AUTH_DURATION = 600;

    public static function assertValidRequest(): void
    {
        $Request = QUI::getRequest();

        if (!$Request->isMethod('POST')) {
            throw new Exception('Method not allowed.', 405);
        }

        CsrfToken::assertValid($Request->request->all()['_csrf'] ?? null);
    }

    /**
     * Only the completed Core login event records recent authentication, including configured MFA.
     */
    public static function onUserLogin(User $User): void
    {
        $Session = QUI::getSession();
        $Session->remove(self::RECENT_AUTH_SESSION_KEY);

        if (!self::hasAuthenticatedSession($User)) {
            return;
        }

        $Session->set(self::RECENT_AUTH_SESSION_KEY, [
            'uuid' => $User->getUUID(),
            'time' => time()
        ]);
    }

    public static function assertRecentAuthentication(User $User): void
    {
        $authentication = QUI::getSession()->get(self::RECENT_AUTH_SESSION_KEY);
        $now = time();

        if (
            !self::hasAuthenticatedSession($User)
            || !is_array($authentication)
            || ($authentication['uuid'] ?? null) !== $User->getUUID()
            || !is_int($authentication['time'] ?? null)
            || $authentication['time'] > $now
            || $authentication['time'] < $now - self::RECENT_AUTH_DURATION
        ) {
            throw new Exception([
                'quiqqer/frontend-users',
                'exception.profile.email_change.recent_auth_required'
            ], 403);
        }
    }

    private static function hasAuthenticatedSession(User $User): bool
    {
        $Session = QUI::getSession();

        return !QUI::getUsers()->isNobodyUser($User)
            && $Session->get('auth') === 1
            && $Session->get('auth-primary') === 1
            && $Session->get('uid') === $User->getUUID()
            && (!QUI::conf('auth_settings', 'secondary_frontend') || $Session->get('auth-secondary') === 1);
    }
}
