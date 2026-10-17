<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
use QUI\ExceptionStack;
use QUI\Verification\Entity\AbstractVerification;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;
use QUI\Verification\Enum\VerificationStatus;
use QUI\Verification\VerificationRepository;

/**
 * Class UserDeleteConfirmVerification
 *
 * User verification to confirm user account deletion
 *
 * @package QUI\FrontendUsers
 */
class UserDeleteConfirmLinkVerification extends AbstractFrontendUsersLinkVerificationHandler
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
     * @throws QUI\Database\Exception
     * @throws \Exception
     */
    public function onSuccess(LinkVerification $verification): void
    {
        // The link only verifies the mail step. Deletion requires a separate profile POST.
    }

    public function confirmDeletion(LinkVerification $verification): void
    {
        ProfileSecurity::assertValidRequest();
        $User = QUI::getUserBySession();

        try {
            ProfileSecurity::assertRecentAuthentication($User);
        } catch (QUI\FrontendUsers\Exception) {
            throw new QUI\FrontendUsers\Exception([
                'quiqqer/frontend-users',
                'exception.profile.deleteaccount.recent_auth_required'
            ], 403);
        }

        // Reload the current request: a supplied or previously loaded object is not authorization.
        $Repository = new VerificationRepository();
        $current = $Repository->findByIdentifier('confirmdelete-' . $User->getUUID());

        if (
            !($current instanceof LinkVerification)
            || $current->uuid !== $verification->uuid
            || $current->getCustomDataEntry('uuid') !== $User->getUUID()
            || $current->status !== VerificationStatus::VERIFIED
            || !$current->isValid()
            || !($Repository->getVerificationHandler($current) instanceof self)
        ) {
            throw new QUI\FrontendUsers\Exception([
                'quiqqer/frontend-users', 'profile.deleteaccount.message.error'
            ], 403);
        }

        Controls\Profile\DeleteAccount::checkDeleteAccount();
        $userProfileSettings = Handler::getInstance()->getUserProfileSettings();

        try {
            // Claim the verified request once, including concurrent confirmations and cancellation.
            $claimed = QUI::getDataBaseConnection()->delete(
                QUI::getDBTableName(VerificationRepository::TBL_VERIFICATION_PROCESSES),
                ['uuid' => $current->uuid, 'status' => VerificationStatus::VERIFIED->value]
            );

            if ($claimed !== 1) {
                throw new QUI\FrontendUsers\Exception([
                    'quiqqer/frontend-users', 'profile.deleteaccount.message.error'
                ], 403);
            }

            switch ($userProfileSettings['userDeleteMode']) {
                case 'delete':
                    QUI::getDataBaseConnection()->update(
                        QUI::getDBTableName('users'),
                        ['active' => -1],
                        ['uuid' => $User->getUUID()]
                    );
                    break;

                case 'wipe':
                    $User->disable(QUI::getUsers()->getSystemUser());
                    break;

                case 'destroy':
                    $User->delete(QUI::getUsers()->getSystemUser());
                    break;
            }

            QUI::getEvents()->fireEvent('quiqqerFrontendUsersUserDelete', [$User]);

            $User->logout();
        } catch (\Doctrine\DBAL\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            throw new QUI\Database\Exception(
                $Exception->getMessage(),
                (int)$Exception->getCode()
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
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @param LinkVerification $verification
     * @return string
     */
    public function getSuccessMessage(LinkVerification $verification): string
    {
        return QUI::getLocale()->get(
            'quiqqer/frontend-users',
            'profile.deleteaccount.message.confirm_ready'
        );
    }

    /**
     * This message is displayed to the user on unsuccessful verification
     *
     * @param LinkVerification $verification
     * @param VerificationErrorReason $reason - The reason for the error (see \QUI\Verification\Verifier::REASON_)
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
     * @throws ExceptionStack
     */
    public function getOnSuccessRedirectUrl(LinkVerification $verification): ?string
    {
        $project = $this->getProject($verification);

        if (!$project) {
            return null;
        }

        $PermissionUser = QUI::getUserBySession();
        QUI\Permissions\Permission::setUser(QUI::getUsers()->getSystemUser());

        try {
            $ProfileSite = Handler::getInstance()->getProfileSite($project);
        } finally {
            QUI\Permissions\Permission::setUser($PermissionUser);
        }

        if (!$ProfileSite) {
            return null;
        }

        return rtrim($ProfileSite->getUrlRewritten(), '/') . '/user/deleteaccount';
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
        if ($reason === VerificationErrorReason::ALREADY_VERIFIED && $verification->isValid()) {
            return $this->getOnSuccessRedirectUrl($verification);
        }

        $project = $this->getProject($verification);

        if (!$project) {
            return null;
        }

        $RegistrationSite = Handler::getInstance()->getRegistrationSignUpSite($project);

        if (!$RegistrationSite) {
            return null;
        }

        return $RegistrationSite->getUrlRewritten([], [
            'error' => 'userdelete'
        ]);
    }
}
