<?php

/**
 * return the registrar control
 *
 * @param string $registrar
 * @return string
 */

use QUI\FrontendUsers\Handler;

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_getControl',
    function ($registrar) {
        $Registrar = Handler::getInstance()->getRegistrarByHash($registrar);

        $Output = new QUI\Output();
        $control = $Registrar->getControl()->create();
        $css = QUI\Control\Manager::getCSS();

        return $Output->parse($css . $control);
    },
    ['registrar']
);
