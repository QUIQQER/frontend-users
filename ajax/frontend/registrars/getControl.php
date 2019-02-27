<?php

use QUI\FrontendUsers\Handler;

/**
 * return the registrar control
 *
 * @param string $registrar
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_getControl',
    function ($registrar) {
        $Registrar = Handler::getInstance()->getRegistrarByHash($registrar);

        return $Registrar->getControl()->create();
    },
    ['registrar']
);
