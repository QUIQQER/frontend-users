<?php

/**
 * This file contains package_quiqqer_invoice_ajax_frontend_address_getDelete
 */

/**
 *
 * @param int $addressId
 * @return string
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_address_getDelete',
    function ($addressId) {
        $_REQUEST['delete'] = $addressId;
        $Address = new QUI\FrontendUsers\Controls\Address\Address();

        return QUI\ControlUtils::parse($Address);
    },
    ['addressId'],
    ['Permission::checkUser']
);
