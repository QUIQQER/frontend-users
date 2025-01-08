<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\DeleteAccount
 */

namespace QUI\FrontendUsers\Controls\Profile;

use Exception;
use QUI;
use QUI\FrontendUsers\Handler;
use QUI\System\Log;
use QUI\Verification\Interface\VerificationRepositoryInterface;
use QUI\Verification\VerificationRepository;

/**
 * Class DeleteAccount
 *
 * Delete user account
 */
class DeleteAccount extends AbstractProfileControl
{
    /**
     * DeleteAccount constructor.
     * @param array $attributes
     */
    public function __construct(
        array $attributes = [],
        private ?VerificationRepositoryInterface $verificationRepository = null
    ) {
        if (is_null($this->verificationRepository)) {
            $this->verificationRepository = new VerificationRepository();
        }

        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-deleteaccount');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');
        $this->addCSSFile(dirname(__FILE__) . '/DeleteAccount.css');

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

        try {
            $verification = $this->verificationRepository->findByIdentifier(
                'confirmdelete-' . QUI::getUserBySession()->getUUID()
            );

            if ($verification) {
                if ($verification->isValid()) {
                    $action = 'deleteaccount_confirm_wait';
                    $this->setJavaScriptControlOption('deletestarted', 1);
                } else {
                    $this->verificationRepository->delete($verification);
                }
            }
        } catch (Exception) {
            // nothing - no active user delete verification
        }

        if (empty($action) && !empty($_GET['action'])) {
            $action = $_GET['action'];
        }

        $Engine->assign([
            'User' => QUI::getUserBySession(),
            'action' => $action
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/DeleteAccount.html');
    }

    /**
     * event: on save
     *
     * @throws Exception
     */
    public function onSave(): void
    {
        self::checkDeleteAccount();

        try {
            Handler::getInstance()->sendDeleteUserConfirmationMail(
                QUI::getUserBySession(),
                QUI::getRewrite()->getProject()
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
}
