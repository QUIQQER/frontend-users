<?php

/**
 * check, if this is a username which can be used
 *
 * @param string $username
 *
 * @return boolean
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_userExists',
    function ($username) {
        return QUI::getUsers()->usernameExists($username);
    },
    ['username']
);
