<?php

use QUI\Utils\Security\Orthos;

/**
 * check, if this is a username which can be used
 *
 * @param string $email
 * @return boolean - true: valid; false: invalid
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_validateEmailSyntax',
    function ($email) {
        return Orthos::checkMailSyntax($email);
    },
    array('email')
);
