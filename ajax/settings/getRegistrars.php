<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_settings_getRegistrars
 */

use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\AbstractRegistrar;

/**
 * Return list of title, description and type of all registrars
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_settings_getRegistrars',
    function () {
        $registrars = [];

        /** @var AbstractRegistrar $Registrar */
        foreach (Handler::getInstance()->getAvailableRegistrars() as $Registrar) {
            $registrars[] = [
                'type'        => $Registrar->getType(),
                'title'       => $Registrar->getTitle(),
                'description' => $Registrar->getDescription()
            ];
        }

        return $registrars;
    },
    [],
    'Permission::checkAdminUser'
);
