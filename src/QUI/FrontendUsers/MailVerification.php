<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Verification\AbstractVerification;

class MailVerification extends AbstractVerification
{
    /**
     * Get the duration of a Verification (minutes)
     *
     * @return int|false - duration in minutes;
     * if this method returns false use the module setting default value
     */
    public function getValidDuration()
    {
        $settings = Handler::getInstance()->getRegistrationSettings();
        return (int)$settings['mailVerificationValidityDuration'];
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
            $User = QUI::getUsers()->get($userId);
            $User->activate(false, QUI::getUsers()->getSystemUser());
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
        // TODO: Implement getSuccessMessage() method.
    }

    /**
     * This message is displayed to the user on unsuccessful verification
     *
     * @param string $reason - The reason for the error (see \QUI\Verification\Verifier::REASON_)
     * @return string
     */
    public function getErrorMessage($reason)
    {
        // TODO: Implement getErrorMessage() method.
    }

    /**
     * Automatically redirect the user to this URL on successful verification
     *
     * @return string|false - If this method returns false, no redirection takes place
     */
    public function getOnSuccessRedirectUrl()
    {
        $RegistrationSite = Handler::getInstance()->getRegistrationSite(
            QUI::getRewrite()->getProject()
        );

        if (!$RegistrationSite) {
            return false;
        }

        return $RegistrationSite->getUrlRewritten(array(
            'status' => 'success'
        ));
    }

    /**
     * Automatically redirect the user to this URL on unsuccessful verification
     *
     * @return string|false - If this method returns false, no redirection takes place
     */
    public function getOnErrorRedirectUrl()
    {
        $RegistrationSite = Handler::getInstance()->getRegistrationSite(
            QUI::getRewrite()->getProject()
        );

        if (!$RegistrationSite) {
            return false;
        }

        return $RegistrationSite->getUrlRewritten(array(
            'status' => 'error'
        ));
    }
}
