<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\DeleteAccount
 */

namespace QUI\FrontendUsers\Controls\Profile;

use Exception;
use QUI;
use QUI\FrontendUsers\Handler;
use QUI\System\Log;
use QUI\Verification\Verifier;

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
    public function __construct(array $attributes = [])
    {
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

        try {
            $DeleteVerification = Verifier::getVerificationByIdentifier(
                QUI::getUserBySession()->getUUID(),
                QUI\FrontendUsers\UserDeleteConfirmVerification::getType(),
                true
            );

            if (Verifier::isVerificationValid($DeleteVerification)) {
                $action = 'deleteaccount_confirm_wait';
                $this->setJavaScriptControlOption('deletestarted', 1);
            } else {
                Verifier::removeVerification($DeleteVerification);
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

        return $Engine->fetch($this->getTemplateFile());
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
