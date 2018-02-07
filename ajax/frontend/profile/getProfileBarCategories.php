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
            foreach ($data['items'] as $key => $item) {
                if (!Utils::hasPermissionToViewCategory($data['name'], $item['name'])) {
                    unset($categories[$k]['items'][$key]);
                }
            }
        }

        $categories = utils::loadTranslationForCategories($categories);
        $categories = utils::setUrlsToCategorySettings($categories);

        return $categories;
    },
    false
);
