<?php

/**
 * return the login control
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_login_getControl',
    function () {
        $Login = new QUI\FrontendUsers\Controls\Login();

        $Output  = new QUI\Output();
        $control = $Login->create();
        $css     = QUI\Control\Manager::getCSS();

        return $Output->parse($css.$control);
    },
    false
);
