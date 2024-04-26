<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
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
     * @throws Exception
     */
    public function getValidDuration(): bool|int
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
            $User = QUI::getUsers()->get($userId);
            $User->activate(false, QUI::getUsers()->getSystemUser());

            Utils::setUserEmailVerified($User);

            QUI::getEvents()->fireEvent(
                'quiqqerFrontendUsersUserActivate',
                [
                    $User,
                    Handler::getInstance()->getRegistrar($User->getAttribute(Handler::USER_ATTR_REGISTRAR))
                ]
            );
        } catch (QUI\Users\Exception $Exception) {
            QUI\System\Log::addWarning(
                'quiqqer/frontend-users -> ActivationVerification :: ' . $Exception->getMessage()
            );
        } catch (\Exception $Exception) {
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
     * @throws Exception
     */
    public function getSuccessMessage(): string
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
     * @param string $reason - The reason for the error (see \QUI\Verification\Verifier::REASON_)
     * @return string
     */
    public function getErrorMessage($reason): string
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
        $RegistrarHandler = Handler::getInstance();
        $RegistrationSite = $RegistrarHandler->getRegistrationSignUpSite(
            $this->getProject()
        );

        if (empty($RegistrationSite)) {
            $RegistrationSite = $RegistrarHandler->getRegistrationSite(
                $this->getProject()
            );

            if (empty($RegistrationSite)) {
                return false;
            }
        }

        return $RegistrationSite->getUrlRewritten([
            'success'
        ], [
            'success' => 'activation',
            'registrar' => $this->getRegistrarHash()
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
        $RegistrarHandler = Handler::getInstance();
        $RegistrationSite = $RegistrarHandler->getRegistrationSignUpSite(
            $this->getProject()
        );

        if (empty($RegistrationSite)) {
            $RegistrationSite = $RegistrarHandler->getRegistrationSite(
                $this->getProject()
            );

            if (empty($RegistrationSite)) {
                return false;
            }
        }

        return $RegistrationSite->getUrlRewritten([
            'error'
        ], [
            'error' => 'activation',
            'registrar' => $this->getRegistrarHash()
        ]);
    }

    /**
     * Get the Project this ActivationVerification is intended for
     *
     * @return QUI\Projects\Project
     * @throws Exception
     */
    protected function getProject(): QUI\Projects\Project
    {
        $additionalData = $this->getAdditionalData();
        return QUI::getProjectManager()->getProject($additionalData['project'], $additionalData['projectLang']);
    }

    /**
     * Get hash of registrar used for this Verification
     *
     * @return string
     */
    protected function getRegistrarHash(): string
    {
        $data = $this->getAdditionalData();

        if (empty($data['registrar'])) {
            return '';
        }

        return $data['registrar'];
    }
}
