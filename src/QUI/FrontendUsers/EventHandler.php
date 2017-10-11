<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Package\Package;

class EventHandler
{
    /**
     * @param Package $Package
     */
    public static function onPackageSetup(Package $Package)
    {
        // create auth provider as user permissions
        $registrarProviders = $Package->getProvider('registrar');

        if (empty($registrarProviders)) {
            return;
        }

        // <permission name="quiqqer.auth.AUTH.canUse" type="bool" />
        $Locale      = new QUI\Locale();
        $Permissions = new QUI\Permissions\Manager();
        $User        = QUI::getUserBySession();

        $Locale->no_translation = true;

        foreach ($registrarProviders as $registrarProvider) {
            continue;

            /* @var $Registrar RegistrarInterface */
            $Authenticator  = new $registrarProvider($User->getUsername());
            $permissionName = Helper::parseAuthenticatorToPermission($authProvider);

            $Permissions->addPermission(array(
                'name'         => $permissionName,
                'title'        => str_replace(array('[', ']'), '', $Authenticator->getTitle($Locale)),
                'desc'         => str_replace(array('[', ']'), '', $Authenticator->getDescription($Locale)),
                'type'         => 'bool',
                'area'         => '',
                'src'          => $Package->getName(),
                'defaultvalue' => 0
            ));
        }
    }
}