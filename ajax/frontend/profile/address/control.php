<?php

/**
 * This file contains package_quiqqer_invoice_ajax_address_create
 */

/**
 * Creates a new invoice address for the user
 *
 * @param int $userId
 * @param array $data
 * @return string
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_address_control',
    function () {
        $Address = new QUI\FrontendUsers\Controls\Address\Address();

        return QUI\ControlUtils::parse($Address);
    },
    false,
    ['Permission::checkUser']
);
