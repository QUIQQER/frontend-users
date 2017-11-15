<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_auth_resendActivationMail
 */

use QUI\Verification\Verifier;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\ActivationVerification;

/**
 * Resend an activation mail
 *
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_auth_resendActivationMail',
    function ($userId) {
        try {
            Verifier::getVerificationByIdentifier((int)$userId, ActivationVerification::getType());
        } catch (\Exception $Exception) {
            // if the verification does not exist -> do not resend mail
            return false;
        }

        try {
            $User           = QUI::getUsers()->get((int)$userId);
            $registrarClass = $User->getAttribute(Handler::USER_ATTR_REGISTRAR);

            /** @var \QUI\FrontendUsers\RegistrarInterface $Registrar */
            $Registrar      = new $registrarClass();
            $Registrar->setProject(QUI::getRewrite()->getProject());

            Handler::getInstance()->sendActivationMail($User, $Registrar);
        } catch (\Exception $Exception) {
            return false;
        }

        return true;
    },
    array('userId')
);