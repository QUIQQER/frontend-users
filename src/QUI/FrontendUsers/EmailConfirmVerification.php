<?php

namespace QUI\FrontendUsers;

use QUI;
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
     * @return int|false - duration in minutes;
     * if this method returns false use the module setting default value
     */
    public function getValidDuration()
    {
        $settings = Handler::getInstance()->getMailSettings();
        return (int)$settings['verificationValidityDuration'];
    }

    /**
     * Execute this method on successful verification
     *
     * @return void
     */
    public function onSuccess()
    {
        $userId = (int)$this->getIdentifier();

        try {
            $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
            $User             = QUI::getUsers()->get($userId);
            $newEmail         = $this->additionalData['newEmail'];

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
    public function onError()
    {
        // nothing
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @return string
     */
    public function getSuccessMessage()
    {
        return '';
    }

    /**
     * This message is displayed to the user on unsuccessful verification
     *
     * @param string $reason - The reason for the error (see \QUI\Verification\Verifier::REASON_)
     * @return string
     */
    public function getErrorMessage($reason)
    {
        return '';
    }

    /**
     * Automatically redirect the user to this URL on successful verification
     *
     * @return string|false - If this method returns false, no redirection takes place
     */
    public function getOnSuccessRedirectUrl()
    {
        $ProfileSite = Handler::getInstance()->getProfileSite(
            $this->getProject()
        );

        if (!$ProfileSite) {
            return false;
        }

        return $ProfileSite->getUrlRewritten(array(
            'user'
        ), array(
            'action' => 'change_email_success'
        ));
    }

    /**
     * Automatically redirect the user to this URL on unsuccessful verification
     *
     * @return string|false - If this method returns false, no redirection takes place
     */
    public function getOnErrorRedirectUrl()
    {
        $ProfileSite = Handler::getInstance()->getRegistrationSite(
            QUI::getRewrite()->getProject()
        );

        if (!$ProfileSite) {
            return false;
        }

        return $ProfileSite->getUrlRewritten(array(
            'user'
        ), array(
            'action' => 'change_email_error'
        ));
    }

    /**
     * Get the Project this Verification is intended for
     *
     * @return QUI\Projects\Project
     */
    protected function getProject()
    {
        $additionalData = $this->getAdditionalData();

        return QUI::getProjectManager()->getProject(
            $additionalData['project'],
            $additionalData['projectLang']
        );
    }
}
