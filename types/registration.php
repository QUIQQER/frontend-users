<?php

use QUI\FrontendUsers\Handler as FrontendUsersHandler;

// AGB
$result = $Project->getSites([
    'where' => [
        'type' => 'quiqqer/intranet:registration/termsOfUse'
    ],
    'limit' => 1
]);


if (isset($result[0])) {
    $Engine->assign('Site_TermsAndConditions', $result[0]);
}

// Privacy
$result = $Project->getSites([
    'where' => [
        'type' => 'quiqqer/intranet:registration/privacy'
    ],
    'limit' => 1
]);

if (isset($result[0])) {
    $Engine->assign('Site_Privacy', $result[0]);
}

$FrontendUsersHandler = FrontendUsersHandler::getInstance();

// check configuration
try {
    $FrontendUsersHandler->checkConfiguration();
} catch (\QUI\FrontendUsers\Exception $Exception) {
    QUI\System\Log::addError(
        'quiqqer/frontend-users is misconfigured: '.$Exception->getMessage()
    );

    $Engine->assign('msg', QUI::getLocale()->get(
        'quiqqer/frontend-users',
        'message.types.registration.configuration_error'
    ));

    exit;
}

$urlParams = QUI::getRewrite()->getUrlParamsList();
$status    = false;

if (!empty($urlParams)) {
    $status = current($urlParams);
}

$Registrar = false;

if (!empty($_REQUEST['registrar'])) {
    try {
        $Registrar = $FrontendUsersHandler->getRegistrarByHash($_REQUEST['registrar']);
    } catch (\Exception $Exception) {
        $Engine->assign('msg', QUI::getLocale()->get(
            'quiqqer/frontend-users',
            'message.types.registration.configuration_error'
        ));
    }
}

// Behaviour if user is already logged in
$loggedIn = boolval(QUI::getUserBySession()->getId());

if ($loggedIn && (!$Registrar || $status === 'error')) {
    $registrationSettings = $FrontendUsersHandler->getRegistrationSettings();

    switch ($registrationSettings['visitRegistrationSiteBehaviour']) {
        case 'showProfile':
            $ProfileSite = $FrontendUsersHandler->getProfileSite($Site->getProject());

            if ($ProfileSite) {
                header('Location: '.$ProfileSite->getUrlRewritten());
                exit;
            }
            break;

        case 'showMessage':
            $Engine->assign('msg', QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'message.types.registration.already_registered'
            ));
            break;
    }
}

/**
 * User Registration
 */
$Registration = new QUI\FrontendUsers\Controls\Registration([
    'status'    => $status,
    'Registrar' => $Registrar
]);

$Engine->assign([
    'Registration' => $Registration,
    'User'         => QUI::getUserBySession()
]);
