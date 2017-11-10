<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_auth_existsUnverifiedActivation
 */

use QUI\Verification\Verifier;

/**
 *
 * @return bool
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_auth_existsUnverifiedActivation',
    function ($userId) {
        try {
            Verifier::getVerificationByIdentifier((int)$userId);
        } catch (\Exception $Exception) {
            return false;
        }

        return true;
    },
    array('userId')
);
