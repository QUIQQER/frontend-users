<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
use QUI\Verification\AbstractVerification;

/**
 * Class EmailConfirmVerification
 *
 * User verification to confirm new e-mail-address
 *
 * @package QUI\FrontendUsers
 */
class EmailConfirmVerification extends AbstractVerification
{
    /**
     * Get the duration of a Verification (minutes)
     *
     * @return int - duration in minutes;
     * if this method returns false use the module setting default value
     * @throws Exception
     */
    public function getValidDuration(): int
    {
        $settings = Handler::getInstance()->getMailSettings();
        return (int)$settings['verificationValidityDuration'];
    }

    /**
     * Execute this method on successful verification
     *
     * @return void
     */
    public function onSuccess(): void
    {
        $userId = $this->getIdentifier();

        try {
            $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
            $User = QUI::getUsers()->get($userId);
            $newEmail = $this->additionalData['newEmail'];

            // if users cannot set their own username -> change username as well
            // if it equals the old email-address
            if (!$RegistrarHandler->isUsernameInputAllowed()) {
                $oldEmail = $User->getAttribute('email');
                $username = $User->getUsername();

                if ($oldEmail === $username) {
                    $User->setAttribute('username', $newEmail);
                }
            }

            $User->setAttribute('email', $newEmail);
            $User->save(QUI::getUsers()->getSystemUser());
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: onSuccess -> Could not find user #' . $userId
            );

            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Execute this method on unsuccessful verification
     *
     * @return void
     */
    public function onError(): void
    {
        // nothing
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @return string
     */
    public function getSuccessMessage(): string
    {
        return '';
    }

    /**
     * This message is displayed to the user on unsuccessful verification
     *
     * @param string $reason - The reason for the error (see \QUI\Verification\Verifier::REASON_)
     * @return string
     */
    public function getErrorMessage(string $reason): string
    {
        return '';
    }

    /**
     * Automatically redirect the user to this URL on successful verification
     *
     * @return string|false - If this method returns false, no redirection takes place
     * @throws Exception
     */
    public function getOnSuccessRedirectUrl(): bool|string
    {
        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite(
            $this->getProject()
        );

        if (!$RegistrationSite) {
            return false;
        }

        return $RegistrationSite->getUrlRewritten([], [
            'success' => 'emailconfirm'
        ]);
    }

    /**
     * Automatically redirect the user to this URL on unsuccessful verification
     *
     * @return string|false - If this method returns false, no redirection takes place
     * @throws Exception
     */
    public function getOnErrorRedirectUrl(): bool|string
    {
        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite(
            $this->getProject()
        );

        if (!$RegistrationSite) {
            return false;
        }

        return $RegistrationSite->getUrlRewritten([], [
            'error' => 'emailconfirm'
        ]);
    }

    /**
     * Get the Project this Verification is intended for
     *
     * @return QUI\Projects\Project
     * @throws Exception
     */
    protected function getProject(): QUI\Projects\Project
    {
        $additionalData = $this->getAdditionalData();

        return QUI::getProjectManager()->getProject(
            $additionalData['project'],
            $additionalData['projectLang']
        );
    }
}
