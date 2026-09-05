<?php

namespace QUI\FrontendUsers;

use Closure;
use QUI;
use QUI\Users\Auth\QUIQQER as PasswordAuthenticator;
use QUI\Users\Auth\SessionFailureCounter;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationStatus;
use QUI\Verification\VerificationRepository;

/** Password proof for the existing activation lookup; it never authenticates a session. */
final class ActivationLookup
{
    public const SESSION_KEY = 'quiqqer.frontend-users.activationLookup';
    public const DURATION = 300;

    private static ?Closure $loginWrapper = null;
    private static bool $passwordAttempt = false;
    private static ?QUI\Users\Exception $inactiveError = null;

    /** @var array{uuid: string, time: int}|null */
    private static ?array $pendingProof = null;

    /**
     * Keep Core's login and its arguments intact, but preserve the narrow proof after
     * Core destroys the authentication session on an inactive-account login error.
     *
     * @param array<string, mixed> $params
     */
    public static function onAjaxCallBefore(string $function, array $params): void
    {
        if ($function !== 'ajax_users_login' || !QUI::isFrontend()) {
            return;
        }

        $registration = QUI::getAjax()->getRegisteredCallables()[$function] ?? null;

        if ($registration === null || $registration['callable'] === self::$loginWrapper) {
            return;
        }

        $original = $registration['callable'];
        self::$loginWrapper = static function (
            $authenticator,
            $params,
            $authStep,
            null|string|array $authenticators = null
        ) use ($original) {
            $Session = QUI::getSession();
            $primary = $authStep !== SessionFailureCounter::STEP_SECONDARY;
            self::$passwordAttempt = $primary
                && $authenticator === PasswordAuthenticator::class
                && !$Session->get('auth-' . PasswordAuthenticator::class);
            self::$inactiveError = null;
            self::$pendingProof = null;

            if ($primary) {
                $Session->remove(self::SESSION_KEY);
            }

            try {
                $result = $original($authenticator, $params, $authStep, $authenticators);

                // A primary password step may precede MFA. This proof authorizes only
                // the email lookup, never login, activation, profile access or MFA bypass.
                if (self::$passwordAttempt && $Session->get('auth-' . PasswordAuthenticator::class) === 1) {
                    $uuid = $Session->get('uid');

                    if (is_string($uuid) && $uuid !== '' && !$Session->get('auth')) {
                        self::saveProof(['uuid' => $uuid, 'time' => time()]);
                    }
                }

                return $result;
            } catch (\Exception $Exception) {
                $Session->remove(self::SESSION_KEY);

                self::preserveProof($Exception);

                throw $Exception;
            } finally {
                self::$passwordAttempt = false;
                self::$inactiveError = null;
                self::$pendingProof = null;
            }
        };

        QUI::getAjax()->registerFunction($function, self::$loginWrapper, $registration['params']);
    }

    private static function preserveProof(\Exception $Exception): void
    {
        if ($Exception === self::$inactiveError && self::$pendingProof !== null) {
            self::saveProof(self::$pendingProof);
        }
    }

    /** Called by Core after authentication, before it clears an inactive user's session. */
    public static function onUserLoginError(mixed $userId, QUI\Users\Exception $Exception): void
    {
        if (
            !QUI::isFrontend()
            || $Exception->getAttribute('reason') !== QUI\Users\Manager::AUTH_ERROR_USER_NOT_ACTIVE
        ) {
            return;
        }

        $Session = QUI::getSession();
        $uuid = $Session->get('uid');

        if (
            !is_string($uuid) || $uuid === '' || $uuid !== $userId
            || $Session->get('auth-' . PasswordAuthenticator::class) !== 1
            || $Session->get('auth-primary') !== 1
        ) {
            return;
        }

        $proof = self::$passwordAttempt ? ['uuid' => $uuid, 'time' => time()] : self::proof();

        if ($proof !== null && $proof['uuid'] === $uuid) {
            self::$inactiveError = $Exception;
            self::$pendingProof = $proof;
        }
    }

    public static function getEmail(mixed $userId): string|false
    {
        $proof = self::proof();

        if ($proof === null || (!is_string($userId) && !is_int($userId))) {
            return false;
        }

        try {
            $User = QUI::getUsers()->get($userId);

            if ($User->getUUID() !== $proof['uuid'] || (int)$User->getAttribute('active') !== 0) {
                return false;
            }

            $Repository = new VerificationRepository();
            $verification = $Repository->findByIdentifier('activate-' . $User->getUUID());

            if (
                !$verification instanceof LinkVerification
                || $verification->status !== VerificationStatus::PENDING
                || !$verification->isValid()
                || $verification->getCustomDataEntry('uuid') !== $User->getUUID()
                || !$Repository->getVerificationHandler($verification) instanceof ActivationLinkVerification
            ) {
                return false;
            }

            $email = $User->getAttribute('email');
            return is_string($email) ? $email : false;
        } catch (\Exception) {
            return false;
        }
    }

    /** @return array{uuid: string, time: int}|null */
    private static function proof(): ?array
    {
        $proof = QUI::getSession()->get(self::SESSION_KEY);
        $now = time();

        if (
            !is_array($proof) || !is_string($proof['uuid'] ?? null) || $proof['uuid'] === ''
            || !is_int($proof['time'] ?? null) || $proof['time'] > $now
            || $proof['time'] <= $now - self::DURATION
        ) {
            return null;
        }

        return ['uuid' => $proof['uuid'], 'time' => $proof['time']];
    }

    /** @param array{uuid: string, time: int} $proof */
    private static function saveProof(array $proof): void
    {
        $Session = QUI::getSession();
        $Session->start();

        if ($Session->regenerate()) {
            $Session->set(self::SESSION_KEY, $proof);
        }
    }
}
