<?php

/**
 * @return string - User e-mail address
 */

use QUI\FrontendUsers\ActivationVerification;
use QUI\Verification\Verifier;

QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_auth_existsUnverifiedActivation',
    function ($userId) {
        try {
            $User = QUI::getUsers()->get($userId);
            Verifier::getVerificationByIdentifier($User->getUUID(), ActivationVerification::getType());
        } catch (Exception) {
            return false;
        }

        return $User->getAttribute('email');
    },
    ['userId']
);
