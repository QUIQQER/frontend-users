<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
use QUI\ExceptionStack;
use QUI\Projects\Project;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;
use QUI\Verification\Entity\AbstractVerification;

/**
 * Class ActivationVerification
 *
 * User verification for account activation
 *
 * @package QUI\FrontendUsers
 */
class ActivationLinkVerification extends AbstractFrontendUsersLinkVerificationHandler
{
    /**
     * Get the duration of a Verification (minutes)
     *
     * @param AbstractVerification $verification
     * @return int|null - duration in minutes;
     * if this method returns false use the module setting default value
     * @throws Exception
     */
    public function getValidDuration(AbstractVerification $verification): ?int
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
            $userUuid = $verification->getCustomDataEntry('uuid');
            $User = QUI::getUsers()->get($userUuid);
            $User->activate('', QUI::getUsers()->getSystemUser());

            Utils::setDefaultUserEmailVerified($User);

            QUI::getEvents()->fireEvent(
                'quiqqerFrontendUsersUserActivate',
                [
                    $User,
                    Handler::getInstance()->getRegistrar($User->getAttribute(Handler::USER_ATTR_REGISTRAR))
                ]
            );
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
     * @throws Exception
     */
    public function getSuccessMessage(LinkVerification $verification): string
    {
        $registrationSetting = Handler::getInstance()->getRegistrationSettings();

        if (!empty($registrationSetting['sendPassword'])) {
            $var = 'verification.ActivationVerification.success_send_password';
        } else {
            $var = 'verification.ActivationVerification.success';
        }

        return QUI::getLocale()->get('quiqqer/frontend-users', $var);
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

        $RegistrarHandler = Handler::getInstance();
        $RegistrationSite = $RegistrarHandler->getRegistrationSignUpSite($project);

        if (empty($RegistrationSite)) {
            $RegistrationSite = $RegistrarHandler->getRegistrationSite($project);

            if (empty($RegistrationSite)) {
                return null;
            }
        }

        return $RegistrationSite->getUrlRewritten([
            'success'
        ], [
            'success' => 'activation',
            'registrar' => $this->getRegistrarHash($verification)
        ]);
    }

    /**
     * Automatically redirect the user to this URL on unsuccessful verification
     *
     * @param LinkVerification $verification
     * @param VerificationErrorReason $reason
     * @return string|null - If this method returns false, no redirection takes place
     * @throws Exception
     * @throws ExceptionStack
     */
    public function getOnErrorRedirectUrl(LinkVerification $verification, VerificationErrorReason $reason): ?string
    {
        $RegistrarHandler = Handler::getInstance();
        $project = $this->getProject($verification);

        if (!$project) {
            return null;
        }

        $RegistrationSite = $RegistrarHandler->getRegistrationSignUpSite($project);

        if (empty($RegistrationSite)) {
            $RegistrationSite = $RegistrarHandler->getRegistrationSite($project);

            if (empty($RegistrationSite)) {
                return null;
            }
        }

        return $RegistrationSite->getUrlRewritten([
            'error'
        ], [
            'error' => 'activation',
            'registrar' => $this->getRegistrarHash($verification)
        ]);
    }

    /**
     * Get hash of registrar used for this Verification
     *
     * @param LinkVerification $verification
     * @return string
     */
    protected function getRegistrarHash(LinkVerification $verification): string
    {
        $registrar = $verification->getCustomDataEntry('registrar');

        if (empty($registrar)) {
            return '';
        }

        return $registrar;
    }
}
