<?php

/**
 * Return list of title, description and class of all authenticators
 *
 * @return array
 */

use QUI\FrontendUsers\AbstractRegistrar;

QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_settings_getAuthenticators',
    function () {
        $authenticators = [];

        /** @var AbstractRegistrar $Registrar */
        foreach (QUI\Users\Auth\Handler::getInstance()->getAvailableAuthenticators() as $class) {
            /** @var QUI\Users\AbstractAuthenticator $Authenticator */
            $Authenticator = new $class();

            // Some authenticators are always available and cannot be switched off
            if ($class == 'QUI\Users\Auth\QUIQQER') {
                continue;
            }

            $authenticators[] = [
                'title' => $Authenticator->getTitle(),
                'description' => $Authenticator->getDescription(),
                'class' => $class
            ];
        }

        return $authenticators;
    },
    [],
    'Permission::checkAdminUser'
);
