<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_profile_getControl
 */

/**
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_getControl',
    function ($category) {
        $Control = new QUI\FrontendUsers\Controls\Profile();
        $Control->setAttribute('User', QUI::getUserBySession());
        $Control->setAttribute('category', $category);

        $result = QUI\Control\Manager::getCSS();
        $result .= $Control->create();

        return QUI\Output::getInstance()->parse($result);
    },
    array('category')
);
