<?php

/**
 * This file contains the profile site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

$SessionUser = QUI::getUserBySession();

if (QUI::getUsers()->isNobodyUser($SessionUser)) {
    $Control = new QUI\FrontendUsers\Controls\Login();
} else {
    $_REQUEST['_url'] = ltrim($_REQUEST['_url'], '/'); // nginx fix
    $_REQUEST['_url'] = urldecode($_REQUEST['_url']);

    $siteUrl = $Site->getLocation();
    $url = trim($_REQUEST['_url'], '/');

    $requestPart = '';

    // $requestPart = str_replace($siteUrl, '', $url);
    if (str_starts_with($url, $siteUrl)) { // remove only the first part
        $requestPart = substr($url, strlen($siteUrl));
    }

    $requestPart = trim($requestPart, '/');
    $requestPart = explode('/', $requestPart);

    if (isset($requestPart[1])) {
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

$Engine->assign([
    'SessionUser' => $SessionUser,
    'Control' => $Control
]);
