<?php

/**
 * return the authenticator control
 *
 * @return string
 */

use QUI\Projects\Site\Utils as SiteUtils;

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_login_getLoginRedirect',
    function ($project) {
        $Project = QUI\Projects\Manager::decode($project);
        $loginSettings = QUI\FrontendUsers\Handler::getInstance()->getLoginSettings();
        $RedirectSite = false;
        $projectLang = $Project->getLang();

        if (!empty($loginSettings['redirectOnLogin'][$projectLang])) {
            $RedirectSite = SiteUtils::getSiteByLink($loginSettings['redirectOnLogin'][$projectLang]);
        }

        if ($RedirectSite) {
            return $RedirectSite->getUrlRewrittenWithHost();
        }

        return false;
    },
    ['project']
);
