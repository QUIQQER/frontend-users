<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\DeleteAccount
 */

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\FrontendUsers\Handler;
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
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-deleteaccount');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');
        $this->addCSSFile(dirname(__FILE__) . '/DeleteAccount.css');
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $action = false;

        try {
            $DeleteVerification = Verifier::getVerificationByIdentifier(
                QUI::getUserBySession()->getId(),
                QUI\FrontendUsers\UserDeleteConfirmVerification::getType(),
                true
            );

            if (Verifier::isVerificationValid($DeleteVerification)) {
                $action = 'deleteaccount_confirm_wait';
            } else {
                Verifier::removeVerification($DeleteVerification);
            }
        } catch (\Exception $Exception) {
            // nothing - no active user delete verification
        }

        if (empty($action) && !empty($_GET['action'])) {
            $action = $_GET['action'];
        }

        $Engine->assign(array(
            'User'   => QUI::getUserBySession(),
            'action' => $action
        ));

        return $Engine->fetch(dirname(__FILE__) . '/DeleteAccount.html');
    }

    /**
     * event: on save
     */
    public function onSave()
    {
        try {
            Handler::getInstance()->sendDeleteUserConfirmationMail(
                QUI::getUserBySession(),
                QUI::getRewrite()->getProject()
            );
        } catch (\Exception $Exception) {
            // nothing
            \QUI\System\Log::writeException($Exception);
        }
    }
}
