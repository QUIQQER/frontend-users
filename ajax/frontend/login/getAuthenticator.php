<?php

use QUI\FrontendUsers\Handler;

/**
 * return the authenticator control
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_login_getAuthenticator',
    function ($authenticator) {
        $available = QUI\Users\Auth\Handler::getInstance()->getAvailableAuthenticators();
        $available = array_flip($available);

        if (!isset($available[$authenticator])) {
            return '';
        }

        $Authenticator = new $authenticator();
    },
    ['authenticator']
);
