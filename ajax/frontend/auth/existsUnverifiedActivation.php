<?php

/**
 * @return string - User e-mail address
 */

use QUI\FrontendUsers\ActivationLookup;
use QUI\FrontendUsers\RegistrationThrottle;

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_auth_existsUnverifiedActivation',
    function ($userId) {
        RegistrationThrottle::reserveLookup();
        return ActivationLookup::getEmail($userId);
    },
    ['userId']
);
