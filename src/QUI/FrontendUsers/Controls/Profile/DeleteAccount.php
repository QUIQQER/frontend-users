<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\DeleteAccount
 */

namespace QUI\FrontendUsers\Controls\Profile;

use Exception;
use QUI;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\UserDeleteConfirmLinkVerification;
use QUI\System\Log;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationStatus;
use QUI\Verification\Interface\VerificationRepositoryInterface;
use QUI\Verification\VerificationRepository;

/**
 * Class DeleteAccount
 *
 * Delete user account
 */
class DeleteAccount extends AbstractProfileControl
{
    private VerificationRepositoryInterface $verificationRepository;
    private bool $deleted = false;

    /**
     * DeleteAccount constructor.
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        array $attributes = [],
        ?VerificationRepositoryInterface $verificationRepository = null
    ) {
        $this->verificationRepository = $verificationRepository ?? new VerificationRepository();

        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-deleteaccount');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');

        if (!defined('QUIQQER_CONTROL_TEMPLATE_USE_BASIC') || QUIQQER_CONTROL_TEMPLATE_USE_BASIC !== true) {
            $this->addCSSFile(dirname(__FILE__) . '/DeleteAccount.css');
        }

        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount');
        $this->setJavaScriptControlOption('username', QUI::getUserBySession()->getUsername());
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $action = false;

        if ($this->deleted) {
            return '<p role="status">' . htmlspecialchars(QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'message.UserDeleteConfirmVerification.success'
            ), ENT_QUOTES, 'UTF-8') . '</p>';
        }

        try {
            $verification = $this->verificationRepository->findByIdentifier(
                'confirmdelete-' . QUI::getUserBySession()->getUUID()
            );

            if ($verification) {
                if ($verification->isValid()) {
                    $action = 'deleteaccount_confirm_wait';
                    $this->setJavaScriptControlOption('deletestarted', 1);

                    if ($verification->status === VerificationStatus::VERIFIED) {
                        $action = 'deleteaccount_confirm_ready';
                    }
                } else {
                    $this->verificationRepository->delete($verification);
                }
            }
        } catch (Exception) {
            // nothing - no active user delete verification
        }

        if (empty($action) && ($_GET['action'] ?? null) === 'deleteaccount_error') {
            $action = 'deleteaccount_error';
        }

        $Engine->assign([
            'User' => QUI::getUserBySession(),
            'action' => $action
        ]);

        if ($action === 'deleteaccount_confirm_ready') {
            return $Engine->fetch(__DIR__ . '/DeleteAccount.Confirm.html');
        }

        return $Engine->fetch(QUI\FrontendUsers\Utils::getRequiredTemplateFile($this));
    }

    /**
     * event: on save
     *
     * @throws Exception
     */
    public function onSave(): void
    {
        $request = QUI::getRequest()->request;
        $action = $request->get('deleteAccountAction');

        if ($action === 'confirm') {
            $verification = $this->verificationRepository->findByIdentifier(
                'confirmdelete-' . QUI::getUserBySession()->getUUID()
            );

            if (!($verification instanceof LinkVerification)) {
                throw new QUI\FrontendUsers\Exception([
                    'quiqqer/frontend-users', 'profile.deleteaccount.message.error'
                ], 403);
            }

            (new UserDeleteConfirmLinkVerification())->confirmDeletion($verification);
            $this->deleted = true;
            return;
        }

        if ($action === 'cancel') {
            $this->cancelDeleteAccountRequest();
            return;
        }

        self::checkDeleteAccount();

        try {
            $Project = QUI::getRewrite()->getProject();

            if ($Project === null) {
                Log::addError(
                    'Frontend users DeleteAccount::onSave: No current rewrite project is available; '
                    . 'the delete confirmation mail was not sent.'
                );

                return;
            }

            Handler::getInstance()->sendDeleteUserConfirmationMail(
                QUI::getUserBySession(),
                $Project
            );
        } catch (Exception $Exception) {
            Log::writeException($Exception);
        }
    }

    /**
     * Checks if a user account can be deleted
     *
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public static function checkDeleteAccount(): void
    {
        QUI::getEvents()->fireEvent('quiqqerFrontendUsersDeleteAccountCheck', [QUI::getUserBySession()]);
    }

    /**
     * Cancel a pending account deletion verification
     */
    protected function cancelDeleteAccountRequest(): void
    {
        try {
            $verification = $this->verificationRepository->findByIdentifier(
                'confirmdelete-' . QUI::getUserBySession()->getUUID()
            );

            if ($verification) {
                $this->verificationRepository->delete($verification);
            }
        } catch (Exception) {
            // nothing - no active user delete verification
        }
    }
}
