<?php

/**
 * return the login control
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_login_getControl',
    function ($authenticators, $mail, $passwordReset) {
        $Login = new QUI\FrontendUsers\Controls\Login([
            'authenticators' => json_decode($authenticators, true),
            'mail'           => isset($mail) ? $mail : true,
            'passwordReset'  => isset($passwordReset) ? $passwordReset : true
        ]);

        $Output  = new QUI\Output();
        $control = $Login->create();
        $css     = QUI\Control\Manager::getCSS();

        return $Output->parse($css.$control);
    },
    ['authenticators', 'mail', 'passwordReset']
);
