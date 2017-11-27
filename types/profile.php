<?php

$SessionUser = QUI::getUserBySession();

if (QUI::getUsers()->isNobodyUser($SessionUser)) {
    $Control = new QUI\Users\Controls\Login();
} else {
    $_REQUEST['_url'] = ltrim($_REQUEST['_url'], '/'); // nginx fix
    $_REQUEST['_url'] = urldecode($_REQUEST['_url']);

    $siteUrl = $Site->getLocation();
    $url     = trim($_REQUEST['_url'], '/');

    $requestPart = str_replace($siteUrl, '', $url);
    $requestPart = trim($requestPart, '/');
    $requestPart = explode('/', $requestPart);

    if (isset($requestPart[0]) && isset($requestPart[1])) {
        $category = $requestPart[0];
        $settings = $requestPart[1];
    } else {
        $category = false;
        $settings = false;
    }

    $Control = new QUI\FrontendUsers\Controls\Profile();
    $Control->setAttribute('User', $SessionUser);
    $Control->setAttribute('category', $category);
    $Control->setAttribute('settings', $settings);
}

$Engine->assign(array(
    'SessionUser' => $SessionUser,
    'Control'     => $Control
));
