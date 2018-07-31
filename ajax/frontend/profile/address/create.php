<?php

/**
 * This file contains package_quiqqer_invoice_ajax_frontend_address_create
 */

/**
 * Create a new address for the user
 *
 * @param string $data - json array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_address_create',
    function ($data) {
        $Package = QUI::getPackage('quiqqer/frontend-users');
        $Config  = $Package->getConfig();

        if ($Config->get('userProfile', 'useAddressManagement') === false) {
            throw new QUI\Exception([
                'quiqqer/frontend-users',
                'exception.add.address.not.allowed'
            ]);
        }

        $_REQUEST['createSave'] = true;

        // @todo

        $Address = new QUI\FrontendUsers\Controls\Address\Address();
        $Address->createAddress(json_decode($data, true));
    },
    ['data']
);
