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
        $_REQUEST['createSave'] = true;

        $Address = new QUI\FrontendUsers\Controls\Address\Address();
        $Address->createAddress(json_decode($data, true));
    },
    array('data')
);
