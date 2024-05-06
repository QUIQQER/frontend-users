<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_auth_resendActivationMail
 */

use QUI\FrontendUsers\ActivationVerification;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\RegistrarInterface;
use QUI\FrontendUsers\Registrars\Email\Registrar as EmailRegistrar;
use QUI\Utils\Security\Orthos;
use QUI\Verification\Verifier;

/**
 * Resend an activation mail
 *
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_auth_resendActivationMail',
    function ($email) {
        try {
            $User = QUI::getUsers()->getUserByMail(Orthos::clear($email));
            Verifier::getVerificationByIdentifier($User->getUUID(), ActivationVerification::getType());
        } catch (Exception $Exception) {
            // if the verification does not exist -> do not resend mail
            QUI\System\Log::writeException($Exception);
            return false;
        }

        try {
            $registrarClass = $User->getAttribute(Handler::USER_ATTR_REGISTRAR);

            if (empty($registrarClass)) {
                $registrarClass = EmailRegistrar::class;
            }

            /** @var RegistrarInterface $Registrar */
            $Registrar = new $registrarClass();
            $Registrar->setProject(QUI::getRewrite()->getProject());

            Handler::getInstance()->sendActivationMail($User, $Registrar);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        return true;
    },
    ['email']
);
