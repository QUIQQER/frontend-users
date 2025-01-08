<?php

/**
 * @return string
 * @throws \QUI\FrontendUsers\Exception
 */

use QUI\FrontendUsers\Utils;

QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_save',
    function ($category, $settings, $data) {
        $data = json_decode($data);
        $Request = QUI::getRequest();

        foreach ($data as $key => $value) {
            $Request->request->set($key, $value);
        }

        $Request->request->set('profile-save', 1);

        // Check permission
        if (!Utils::hasPermissionToViewCategory($category, $settings)) {
            throw new \QUI\FrontendUsers\Exception([
                'quiqqer/frontend-users',
                'exception.ajax.frontend.profile.save.no_category_permission'
            ]);
        }

        $Control = QUI\FrontendUsers\Utils::getProfileSettingControl($category, $settings);
        $Control->setAttribute('User', QUI::getUserBySession());
        $Control->onSave();
    },
    ['category', 'settings', 'data'],
    ['Permission::checkUser']
);
