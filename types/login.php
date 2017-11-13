<?php

use QUI\FrontendUsers\Controls\Auth\FrontendLogin;
use QUI\Utils\Security\Orthos;
use QUI\FrontendUsers;

$error = false;

// Standard login via POST requests (this most likely means that JavaScript is disabled)
if (!empty($_POST['username'])
    && !empty($_POST['password'])
    && !empty($_POST['login'])) {
    $Users = QUI::getUsers();

    try {
        $User = $Users->getUserByName(Orthos::clear($_POST['username']));

        QUI::getSession()->set('uid', $User->getId());

        // use QUIQQER default authenticator
        QUI::getUsers()->authenticate(
            QUI\Users\Auth\QUIQQER::class,
            $_POST
        );

        QUI::getSession()->set('auth-globals', 1);
        $Users->login();

        // check for redirection
        $loginSettings = FrontendUsers\Handler::getInstance()->getLoginSettings();
        $RedirectSite  = false;

        if (!empty($loginSettings['redirectOnLogin'])) {
            $RedirectSite = QUI\Projects\Site\Utils::getSiteByLink($loginSettings['redirectOnLogin']);
        }

        if (!$RedirectSite) {
            $RedirectSite = QUI::getRewrite()->getProject()->get(1);
        }

        if ($RedirectSite) {
            $url = $RedirectSite->getProject()->getVHost(true) . $RedirectSite->getUrlRewritten();
            header("Location: " . $url);

            exit;
        }
    } catch (QUI\Users\Exception $Exception) {
        $error = $Exception->getMessage();
    } catch (\Exception $Exception) {
        QUI\System\Log::writeException($Exception);
        $error = QUI::getLocale()->get('quiqqer/frontend-users', 'login.general_error');
    }
}

$SessionUser = QUI::getUserBySession();
$isAuth      = boolval($SessionUser->getId());

$Engine->assign(array(
    'isAuth'        => $isAuth,
    'SessionUser'   => $SessionUser,
    'FrontendLogin' => new FrontendLogin(),
    'error'         => $error
));
