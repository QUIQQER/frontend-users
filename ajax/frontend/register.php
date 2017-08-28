<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_register
 */

/**
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_register',
    function ($registrator, $data) {
        $Registration = new QUI\FrontendUsers\Controls\Registration();

        $_POST = array_merge($_POST, json_decode($data, true));

        $_POST['registration'] = 1;
        $_POST['registrator']  = $registrator;

        return $Registration->create();
    },
    array('registrator', 'data')
);
