<?php

/**
 * Checks if an email address is blacklisted.
 *
 * @param string $email
 * @return boolean
 */

use QUI\FrontendUsers\Utils;

QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_emailBlacklisted',
    function ($email) {
        return Utils::isEmailBlacklisted($email);
    },
    ['email']
);
