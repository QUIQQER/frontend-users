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
    'package_quiqqer_frontend-users_ajax_frontend_profile_address_edit',
    function ($addressId, $data) {
        $_REQUEST['addressId'] = $addressId;
        $_REQUEST['editSave']  = true;

        $Address = new QUI\FrontendUsers\Controls\Address\Address();
        $Address->editAddress(json_decode($data, true));
    },
    array('addressId', 'data')
);
