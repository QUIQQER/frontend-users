<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
use QUI\ExceptionStack;
use QUI\Projects\Project;
use QUI\Verification\AbstractVerificationHandler;
use QUI\Verification\Entity\Verification;
use QUI\Verification\Enum\VerificationErrorReason;

/**
 * Class ActivationVerification
 *
 * User verification for account activation
 *
 * @package QUI\FrontendUsers
 */
class ActivationVerification extends AbstractVerificationHandler
{
    /**
     * Get the duration of a Verification (minutes)
     *
     * @return int|null - duration in minutes;
     * if this method returns false use the module setting default value
     * @throws Exception
     */
    public function getValidDuration(): ?int
    {
        $settings = Handler::getInstance()->getMailSettings();
        return (int)$settings['verificationValidityDuration'];
    }

    /**
     * Execute this method on successful verification
     *
     * @param Verification $verification
     * @return void
     */
    public function onSuccess(Verification $verification): void
    {
        try {
            $userUuid = $verification->identifier;
            $User = QUI::getUsers()->get($userUuid);
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
     * @param Verification $verification
     * @return void
     */
    public function onError(Verification $verification): void
    {
        // nothing
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @param Verification $verification
     * @return string
     * @throws Exception
     */
    public function getSuccessMessage(Verification $verification): string
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
     * @param Verification $verification
     * @param string $reason
     * @return string
     */
    public function getErrorMessage(Verification $verification, VerificationErrorReason $reason): string
    {
        return '';
    }

    /**
     * Automatically redirect the user to this URL on successful verification
     *
     * @param Verification $verification
     * @return string|null - If this method returns false, no redirection takes place
     * @throws Exception
     */
    public function getOnSuccessRedirectUrl(Verification $verification): ?string
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
     * @param Verification $verification
     * @return string|null - If this method returns false, no redirection takes place
     * @throws Exception
     * @throws ExceptionStack
     */
    public function getOnErrorRedirectUrl(Verification $verification): ?string
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
     * Get the Project this ActivationVerification is intended for
     *
     * @param Verification $verification
     * @return Project|null
     * @throws Exception
     */
    protected function getProject(Verification $verification): ?QUI\Projects\Project
    {
        $project = $verification->getCustomDataEntry('project');
        $projectLang = $verification->getCustomDataEntry('projectLang');

        if (empty($project) || empty($projectLang)) {
            return null;
        }

        return QUI::getProjectManager()->getProject($project, $projectLang);
    }

    /**
     * Get hash of registrar used for this Verification
     *
     * @param Verification $verification
     * @return string
     */
    protected function getRegistrarHash(Verification $verification): string
    {
        $registrar = $verification->getCustomDataEntry('registrar');

        if (empty($registrar)) {
            return '';
        }

        return $registrar;
    }
}
