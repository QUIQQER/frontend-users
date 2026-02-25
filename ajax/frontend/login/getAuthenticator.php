<?php

/**
 * return the authenticator control
 *
 * @return string
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_login_getAuthenticator',
    function ($authenticator) {
        $available = QUI\Users\Auth\Handler::getInstance()->getAvailableAuthenticators();
        $available = array_flip($available);

        if (!isset($available[$authenticator])) {
            return '';
        }

        new $authenticator();
        return $authenticator;
    },
    ['authenticator']
);
