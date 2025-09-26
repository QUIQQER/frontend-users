<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;
use QUI\Verification\Entity\AbstractVerification;

/**
 * Class EmailConfirmVerification
 *
 * User verification to confirm new e-mail-address
 *
 * @package QUI\FrontendUsers
 */
class EmailConfirmLinkVerification extends AbstractFrontendUsersLinkVerificationHandler
{
    /**
     * Get the duration of a Verification (minutes)
     *
     * @param AbstractVerification $verification
     * @return int - duration in minutes;
     * if this method returns false use the module setting default value
     * @throws Exception
     */
    public function getValidDuration(AbstractVerification $verification): int
    {
        $settings = Handler::getInstance()->getMailSettings();
        return (int)$settings['verificationValidityDuration'];
    }

    /**
     * Execute this method on successful verification
     *
     * @param LinkVerification $verification
     * @return void
     * @throws Exception
     */
    public function onSuccess(LinkVerification $verification): void
    {
        try {
            $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
            $userUuid = $verification->getCustomDataEntry('uuid');
            $User = QUI::getUsers()->get($userUuid);
            $newEmail = $verification->getCustomDataEntry('newEmail');
            $oldEmail = $User->getAttribute('email');

            // if users cannot set their own username -> change username as well
            // if it equals the old email-address
            if (!$RegistrarHandler->isUsernameInputAllowed()) {
                $username = $User->getUsername();

                if ($oldEmail === $username) {
                    $User->setAttribute('username', $newEmail);
                }
            }

            $User->setAttribute('email', $newEmail);
            $User->save(QUI::getUsers()->getSystemUser());

            Utils::setEmailAddressAsVerifiedForUser($newEmail, $User);

            QUI::getEvents()->fireEvent('quiqqerFrontendUsersEmailChanged', [$User, $oldEmail, $newEmail]);
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
        $project = $this->getProject($verification);

        if (!$project) {
            return null;
        }

        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite($project);

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
        $project = $this->getProject($verification);

        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite($project);

        if (!$RegistrationSite) {
            return null;
        }

        return $RegistrationSite->getUrlRewritten([], [
            'error' => 'emailconfirm'
        ]);
    }
}
