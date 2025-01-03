<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
use QUI\Verification\AbstractVerificationHandler;
use QUI\Verification\Entity\Verification;
use QUI\Verification\Enum\VerificationErrorReason;

/**
 * Class EmailConfirmVerification
 *
 * User verification to confirm new e-mail-address
 *
 * @package QUI\FrontendUsers
 */
class EmailConfirmVerification extends AbstractVerificationHandler
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
     * @param Verification $verification
     * @return void
     */
    public function onSuccess(Verification $verification): void
    {
        try {
            $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
            $userUuid = $verification->identifier;
            $User = QUI::getUsers()->get($userUuid);
            $newEmail = $verification->getCustomDataEntry('newEmail');

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
                self::class . ' :: onSuccess -> Could not find user #' . $userUuid
            );

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
     */
    public function getSuccessMessage(Verification $verification): string
    {
        return '';
    }

    /**
     * This message is displayed to the user on unsuccessful verification
     *
     * @param Verification $verification
     * @param VerificationErrorReason $reason
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
     * @param Verification $verification
     * @return string|null - If this method returns false, no redirection takes place
     * @throws Exception
     */
    public function getOnErrorRedirectUrl(Verification $verification): ?string
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

    /**
     * Get the Project this ActivationVerification is intended for
     *
     * @param Verification $verification
     * @return QUI\Projects\Project|null
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
}
