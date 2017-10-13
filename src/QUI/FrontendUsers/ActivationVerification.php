<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Verification\AbstractVerification;

/**
 * Class ActivationVerification
 *
 * User verification for account activation
 *
 * @package QUI\FrontendUsers
 */
class ActivationVerification extends AbstractVerification
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
        return (int)$settings['activationVerificationValidityDuration'];
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

            $User->setAttribute(Handler::USER_ATTR_EMAIL_VERIFIED, true);
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
        $RegistrationSite = Handler::getInstance()->getRegistrationSite(
            $this->getProject()
        );

        if (!$RegistrationSite) {
            return false;
        }

        return $RegistrationSite->getUrlRewritten(array(
            'success'
        ), array(
            'r' => $this->getRegistrarHash()
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
            'error'
        ), array(
            'r' => $this->getRegistrarHash()
        ));
    }

    /**
     * Get the Project this ActivationVerification is intended for
     *
     * @return QUI\Projects\Project
     */
    protected function getProject()
    {
        $additionalData = $this->getAdditionalData();
        return QUI::getProjectManager()->getProject($additionalData['project'], $additionalData['projectLang']);
    }

    /**
     * Get hash of registrar used for this Verification
     *
     * @return string
     */
    protected function getRegistrarHash()
    {
        $data = $this->getAdditionalData();

        if (empty($data['registrar'])) {
            return '';
        }

        return $data['registrar'];
    }
}
