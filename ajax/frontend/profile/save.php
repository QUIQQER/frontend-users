<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_profile_getControl
 */

/**
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_save',
    function ($category, $data) {
        $data    = json_decode($data);
        $Request = QUI::getRequest();

        foreach ($data as $key => $value) {
            $Request->request->set($key, $value);
        }

        $Request->request->set('profile-save', 1);

        $Control = QUI\FrontendUsers\Utils::getProfileCategoryControl($category);
        $Control->setAttribute('User', QUI::getUserBySession());
        $Control->onSave();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'message.user.saved.successfully'
            )
        );
    },
    array('category', 'data')
);
