<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
use QUI\ExceptionStack;
use QUI\Verification\AbstractVerificationHandler;
use QUI\Verification\Enum\VerificationErrorReason;
use QUI\Verification\Entity\Verification;

/**
 * Class UserDeleteConfirmVerification
 *
 * User verification to confirm user account deletion
 *
 * @package QUI\FrontendUsers
 */
class UserDeleteConfirmVerification extends AbstractVerificationHandler
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
     * @throws \Exception
     */
    public function onSuccess(Verification $verification): void
    {
        $userUuid = $verification->identifier;
        $userProfileSettings = Handler::getInstance()->getUserProfileSettings();

        try {
            $User = QUI::getUsers()->get($userUuid);

            switch ($userProfileSettings['userDeleteMode']) {
                case 'delete':
                    QUI::getDataBase()->update(
                        QUI::getDBTableName('users'),
                        ['active' => -1],
                        ['uuid' => $User->getUUID()]
                    );
                    break;

                case 'wipe':
                    $User->disable(QUI::getUsers()->getSystemUser());
                    break;

                case 'destroy':
                    $User->delete();
                    break;
            }

            QUI::getEvents()->fireEvent('quiqqerFrontendUsersUserDelete', [$User]);

            $User->logout();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            QUI\System\Log::addError(
                self::class . ' :: onSuccess -> Could not find/delete user #' . $userUuid
            );

            QUI\System\Log::writeException($Exception);

            throw $Exception;
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
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @param Verification $verification
     * @return string
     */
    public function getSuccessMessage(Verification $verification): string
    {
        return QUI::getLocale()->get(
            'quiqqer/frontend-users',
            'message.UserDeleteConfirmVerification.success'
        );
    }

    /**
     * This message is displayed to the user on unsuccessful verification
     *
     * @param Verification $verification
     * @param VerificationErrorReason $reason - The reason for the error (see \QUI\Verification\Verifier::REASON_)
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
     * @throws ExceptionStack
     */
    public function getOnSuccessRedirectUrl(Verification $verification): ?string
    {
        $project = $this->getProject($verification);

        if (!$project) {
            return null;
        }

        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite($project);

        if (!$RegistrationSite) {
            return false;
        }

        return $RegistrationSite->getUrlRewritten([], [
            'success' => 'userdelete'
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

        if (!$project) {
            return null;
        }

        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite($project);

        if (!$RegistrationSite) {
            return false;
        }

        return $RegistrationSite->getUrlRewritten([], [
            'error' => 'userdelete'
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
