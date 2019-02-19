<?php

/**
 * This file contains package_quiqqer_invoice_ajax_frontend_address_delete
 */

/**
 *
 * @param int $addressId
 * @param array $data
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_address_getCreate',
    function () {
        $_REQUEST['create'] = true;

        $Address = new QUI\FrontendUsers\Controls\Address\Address();

        return QUI\ControlUtils::parse($Address);
    },
    false,
    ['Permission::checkUser']
);
