<?php

/**
 * Get all categories that are to be shown in the profile bar
 *
 * @return array
 */

use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Utils;

QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_getProfileBarCategories',
    function () {
        $ProfileSite = Handler::getInstance()->getProfileSite();

        if (!$ProfileSite) {
            return [];
        }

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

        // Check if "go to profile" button is added
        try {
            $profileBarSettings = Handler::getInstance()->getProfileBarSettings();

            if (!empty($profileBarSettings['showToProfile']) && !empty($categories['user'])) {
                array_unshift($categories['user']['items'], [
                    'name' => 'toprofile',
                    'title' => QUI::getLocale()->get(
                        'quiqqer/frontend-users',
                        'profilebar.to_profile'
                    ),
                    'index' => 0,
                    'icon' => 'fa fa-user',
                    'control' => false,
                    'showinprofilebar' => true,
                    'content' => false,
                    'url' => $ProfileSite->getUrlRewritten()
                ]);
            }
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return $categories;
    },
    false
);
