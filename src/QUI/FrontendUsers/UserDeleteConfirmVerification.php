<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Verification\AbstractVerification;

/**
 * Class UserDeleteConfirmVerification
 *
 * User verification to confirm user account deletion
 *
 * @package QUI\FrontendUsers
 */
class UserDeleteConfirmVerification extends AbstractVerification
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
     * @throws \Exception
     */
    public function onSuccess()
    {
        $userId              = (int)$this->getIdentifier();
        $userProfileSettings = Handler::getInstance()->getUserProfileSettings();

        try {
            $User = QUI::getUsers()->get($userId);

            switch ($userProfileSettings['userDeleteMode']) {
                case 'delete':
                    QUI::getDataBase()->update(
                        QUI::getDBTableName('users'),
                        [
                            'active' => -1
                        ],
                        [
                            'id' => $User->getId()
                        ]
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
            QUI\System\Log::addError(
                self::class.' :: onSuccess -> Could not find/delete user #'.$userId
            );

            QUI\System\Log::writeException($Exception);

            throw $Exception;
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
        return QUI::getLocale()->get(
            'quiqqer/frontend-users',
            'message.UserDeleteConfirmVerification.success'
        );
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
        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite(
            $this->getProject()
        );

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
     * @return string|false - If this method returns false, no redirection takes place
     */
    public function getOnErrorRedirectUrl()
    {
        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite(
            $this->getProject()
        );

        if (!$RegistrationSite) {
            return false;
        }

        return $RegistrationSite->getUrlRewritten([], [
            'error' => 'emailconfirm'
        ]);
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
