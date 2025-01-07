<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;

/**
 * User verification to confirm an e-mail-address
 */
class EmailVerification extends AbstractFrontendUsersLinkVerificationHandler
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
     * @param LinkVerification $verification
     * @return void
     * @throws \Exception
     */
    public function onSuccess(LinkVerification $verification): void
    {
        try {
            $User = QUI::getUsers()->get($verification->getCustomDataEntry('uuid'));
            $email = $verification->getCustomDataEntry('email');

            Utils::setEmailAddressAsVerfifiedForUser($email, $User);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            throw $Exception;
        }
    }

    /**
     * Execute this method on unsuccessful verification
     *
     * @param LinkVerification $verification
     * @param VerificationErrorReason $reason
     * @return void
     */
    public function onError(LinkVerification $verification, VerificationErrorReason $reason): void
    {
        // nothing
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @param LinkVerification $verification
     * @return string
     */
    public function getSuccessMessage(LinkVerification $verification): string
    {
        return '';
    }

    /**
     * This message is displayed to the user on unsuccessful verification
     *
     * @param LinkVerification $verification
     * @param VerificationErrorReason $reason
     * @return string
     */
    public function getErrorMessage(LinkVerification $verification, VerificationErrorReason $reason): string
    {
        return '';
    }

    /**
     * Automatically redirect the user to this URL on successful verification
     *
     * @param LinkVerification $verification
     * @return string|null - If this method returns false, no redirection takes place
     * @throws Exception
     */
    public function getOnSuccessRedirectUrl(LinkVerification $verification): ?string
    {
        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite(
            $this->getProject($verification)
        );

        if (!$RegistrationSite) {
            return null;
        }

        return $RegistrationSite->getUrlRewritten([], [
            'success' => 'emailconfirm'
        ]);
    }

    /**
     * Automatically redirect the user to this URL on unsuccessful verification
     *
     * @param LinkVerification $verification
     * @param VerificationErrorReason $reason
     * @return string|null - If this method returns false, no redirection takes place
     * @throws Exception
     */
    public function getOnErrorRedirectUrl(LinkVerification $verification, VerificationErrorReason $reason): ?string
    {
        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite(
            $this->getProject($verification)
        );

        if (!$RegistrationSite) {
            return null;
        }

        return $RegistrationSite->getUrlRewritten([], [
            'error' => 'emailconfirm'
        ]);
    }
}
