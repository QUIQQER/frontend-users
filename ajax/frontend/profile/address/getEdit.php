<?php

/**
 * This file contains package_quiqqer_invoice_ajax_frontend_address_getEdit
 */

/**
 *
 * @param int $addressId
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_address_getEdit',
    function ($addressId) {
        $_REQUEST['edit'] = $addressId;

        $Address = new QUI\FrontendUsers\Controls\Address\Address();

        return QUI\ControlUtils::parse($Address);
    },
    ['addressId'],
    ['Permission::checkUser']
);
