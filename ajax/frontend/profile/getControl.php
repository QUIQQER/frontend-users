<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_profile_getControl
 */

use QUI\Utils\Security\Orthos;
use QUI\FrontendUsers\Utils;

/**
 * Get profile control by category
 *
 * @param string $category
 * @param string $settings
 * @param string $project
 * @param int $siteId
 * @return false|string - false if category does not exist or user has no permission -> category control html otherwise
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_getControl',
    function ($category, $settings, $project, $siteId, $menu) {
        $category = Orthos::clear($category);
        $settings = Orthos::clear($settings);

        if (!empty($category) && !Utils::hasPermissionToViewCategory($category, $settings)) {
            return false;
        }

        $Control = new QUI\FrontendUsers\Controls\Profile();

        try {
            $Project = QUI::getProjectManager()->decode($project);
            $Control->setAttribute('Site', $Project->get((int)$siteId));
        } catch (\Exception $Exception) {
            // nothing
        }

        $Control->setAttribute('User', QUI::getUserBySession());
        $Control->setAttribute('category', Orthos::clear($category));
        $Control->setAttribute('settings', Orthos::clear($settings));
        $Control->setAttribute('menu', isset($menu) ? $menu : true);

        try {
            $html = $Control->create();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }

        $result = QUI\Control\Manager::getCSS();
        $result .= $html;

        return QUI\Output::getInstance()->parse($result);
    },
    ['category', 'settings', 'project', 'siteId', 'menu']
);
