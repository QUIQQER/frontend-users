<?php

/**
 * return the sign in registration control
 *
 * @return string
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_getSignInControl',
    function () {
        $Registration = new QUI\FrontendUsers\Controls\RegistrationSignUp([
            'content' => ''
        ]);

        return $Registration->create();
    }
);
