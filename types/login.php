<?php

/**
 * This file contains the login site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

use QUI\FrontendUsers;
use QUI\FrontendUsers\Controls\Auth\FrontendLogin;
use QUI\Projects\Site\Utils as SiteUtils;
use QUI\Utils\Security\Orthos;

$error = false;

// Standard login via POST requests (this most likely means that JavaScript is disabled)
if (
    !empty($_POST['username'])
    && !empty($_POST['password'])
    && !empty($_POST['login'])
) {
    $Users = QUI::getUsers();

    try {
        $User = $Users->getUserByName(Orthos::clear($_POST['username']));

        QUI::getSession()->set('uid', $User->getUUID());

        // use QUIQQER default authenticator
        QUI::getUsers()->authenticate(
            QUI\Users\Auth\QUIQQER::class,
            $_POST
        );

        QUI::getSession()->set('auth-globals', 1);
        $Users->login();
    } catch (QUI\Users\Exception $Exception) {
        $error = $Exception->getMessage();
    } catch (\Exception $Exception) {
        QUI\System\Log::writeException($Exception);
        $error = QUI::getLocale()->get('quiqqer/frontend-users', 'login.general_error');
    }
}

$SessionUser = QUI::getUserBySession();
$isAuth = QUI::getUsers()->isAuth($SessionUser);

if ($isAuth) {
    // check for redirection
    $loginSettings = FrontendUsers\Handler::getInstance()->getLoginSettings();
    $RedirectSite = false;
    $projectLang = $Project->getLang();

    if (!empty($loginSettings['redirectOnLogin'][$projectLang])) {
        $RedirectSite = SiteUtils::getSiteByLink($loginSettings['redirectOnLogin'][$projectLang]);
    }

    if (!$RedirectSite && $Site->getId() !== 1) {
        $RedirectSite = QUI::getRewrite()->getProject()->get(1);
    }

    if ($RedirectSite) {
        $url = $RedirectSite->getUrlRewrittenWithHost();
        header("Location: " . $url);

        exit;
    }
}

$Engine->assign([
    'isAuth' => $isAuth,
    'SessionUser' => $SessionUser,
    'FrontendLogin' => new FrontendLogin(),
    'error' => $error
]);
