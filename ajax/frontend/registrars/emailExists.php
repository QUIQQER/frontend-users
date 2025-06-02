<?php

/**
 * check, if this is a email which can be used
 *
 * @param string $username
 *
 * @return boolean
 */

use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_emailExists',
    function ($email) {
        if (!Orthos::checkMailSyntax($email)) {
            return false;
        }

        return QUI::getUsers()->emailExists($email);
    },
    ['email']
);
