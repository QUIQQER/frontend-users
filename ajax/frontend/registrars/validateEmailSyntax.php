<?php

/**
 * check, if this is a username which can be used
 *
 * @param string $email
 * @return boolean - true: valid; false: invalid
 */

use QUI\Utils\Security\Orthos;

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_validateEmailSyntax',
    function ($email) {
        return Orthos::checkMailSyntax($email);
    },
    ['email']
);
