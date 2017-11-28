<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_profile_getProfileBarCategories
 */

use QUI\FrontendUsers\Utils;

/**
 * Get all categories that are to be shown in the profile bar
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_getProfileBarCategories',
    function () {
        $categories = Utils::getProfileBarCategorySettings();

        foreach ($categories as $k => $data) {
            if (!Utils::hasPermissionToViewCategory($data['name'])) {
                unset($categories[$k]);
            }
        }

        $categories = utils::loadTranslationForCategories($categories);

        return $categories;
    },
    false
);
