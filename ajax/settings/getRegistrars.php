<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_settings_getRegistrars
 */

use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\RegistrarInterface;

/**
 * Return list of title, description and type of all registrars
 *
 * @return array
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_settings_getRegistrars',
    function () {
        $registrars = [];

        $Handler = Handler::getInstance();

        /** @var RegistrarInterface $Registrar */
        foreach ($Handler->getAvailableRegistrars() as $Registrar) {
            $registrars[] = [
                'type' => $Registrar->getType(),
                'title' => $Registrar->getTitle(),
                'description' => $Registrar->getDescription(),
                'activationModes' => $Handler->getSupportedActivationModes($Registrar),
                'defaultActivationMode' => $Handler->resolveActivationMode($Registrar, null)
            ];
        }

        return $registrars;
    },
    [],
    'Permission::checkAdminUser'
);
