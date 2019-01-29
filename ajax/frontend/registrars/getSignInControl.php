<?php

/**
 * return the sign in registration control
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_getSignInControl',
    function () {
        $Registration = new QUI\FrontendUsers\Controls\RegistrationSignUp([
            'content' => ''
        ]);

        // @todo use js options
        if (QUI::getPackageManager()->isInstalled('quiqqer/registration-trial')) {
            $Registration->setAttribute('registration-trial', true);
        }

        return $Registration->create();
    },
    []
);
