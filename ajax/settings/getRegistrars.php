<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_settings_getRegistrars
 */

use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\AbstractRegistrator;

/**
 * Return list of title, description and type of all registrars
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_settings_getRegistrars',
    function () {
        $registrars = array();

        /** @var AbstractRegistrator $Registrator */
        foreach (Handler::getInstance()->getRegistrators() as $Registrator) {
            $registrars[] = array(
                'type'        => $Registrator->getType(),
                'title'       => $Registrator->getTitle(),
                'description' => $Registrator->getDescription()
            );
        }

        return $registrars;
    },
    array(),
    'Permission::checkAdminUser'
);
