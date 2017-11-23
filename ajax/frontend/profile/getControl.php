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
 * @return false|string - false if category does not exist or user has no permission -> category control html otherwise
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_getControl',
    function ($category, $project, $siteId) {
        $category = Orthos::clear($category);

        if (!Utils::hasPermissionToViewCategory($category)) {
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

        $result = QUI\Control\Manager::getCSS();
        $result .= $Control->create();

        return QUI\Output::getInstance()->parse($result);
    },
    array('category', 'project', 'siteId')
);
