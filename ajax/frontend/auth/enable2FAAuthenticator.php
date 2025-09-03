<?php

use QUI\Users\Auth\Handler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_auth_enable2FAAuthenticator',
    function ($authenticator) {
        $available = Handler::getInstance()->getAvailableAuthenticators();
        $available = array_flip($available);

        $User = QUI::getUserBySession();
        $User->enableAuthenticator($authenticator);
    },
    ['authenticator'],
    ['Permission::checkUser']
);


