<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_register
 */

/**
 * Start registration process
 *
 * @param string $registrar - Registrar name
 * @param array $data - Registrar attributes
 * @return string - Registrar status message
 *
 * @throws QUI\Exception
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_register',
    function ($registrar, $data) {
        $Registration = new QUI\FrontendUsers\Controls\Registration();

        $_POST = array_merge($_POST, json_decode($data, true));

        $_POST['registration'] = 1;
        $_POST['registrar']    = $registrar;

        // do not show user edit messages
        QUI::getMessagesHandler()->clear();

        try {
            return $Registration->create();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            throw new QUI\Exception(array(
                'quiqqer/frontend-users',
                'exception.ajax.frontend_register.general_error'
            ));
        }
    },
    array('registrar', 'data')
);
