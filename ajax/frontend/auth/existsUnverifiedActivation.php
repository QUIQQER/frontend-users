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
            $User = QUI::getUsers()->get((int)$userId);
            Verifier::getVerificationByIdentifier($User->getId(), ActivationVerification::getType());
        } catch (\Exception $Exception) {
            return false;
        }

        return $User->getAttribute('email');
    },
    ['userId']
);
