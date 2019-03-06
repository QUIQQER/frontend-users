<?php

$Site->setAttribute('nocache', 1);


$background = $Site->getAttribute('quiqqer.sign.up.background');
$Background = null;

$registrars = [];

if ($Site->getAttribute('quiqqer.sign.up.registrars')) {
    $registrars = $Site->getAttribute('quiqqer.sign.up.registrars');
    $registrars = json_decode($registrars, true);
}

if (QUI\Projects\Media\Utils::isMediaUrl($background)) {
    try {
        $Background = QUI\Projects\Media\Utils::getImageByUrl($background);
    } catch (QUI\Exception $exception) {
    }
}

if (QUI::getUserBySession()->getId()) {
    try {
        $FrontendUsersHandler = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $FrontendUsersHandler->getRegistrationSettings();

        if ($registrationSettings['visitRegistrationSiteBehaviour'] === 'showProfile') {
            $ProfileSite = $FrontendUsersHandler->getProfileSite($Site->getProject());

            if ($ProfileSite) {
                header('Location: '.$ProfileSite->getUrlRewritten());
                exit;
            }
        }

        if (!empty($registrationSettings['autoRedirectOnSuccess'])) {
            $current  = QUI::getLocale()->getCurrent();
            $Redirect = null;

            if (isset($registrationSettings['autoRedirectOnSuccess'][$current])) {
                $Redirect = QUI\Projects\Site\Utils::getSiteByLink(
                    $registrationSettings['autoRedirectOnSuccess'][$current]
                );
            }

            if ($Redirect) {
                header('Location: '.$Redirect->getUrlRewritten());
                exit;
            }
        }
    } catch (QUI\Exception $Exception) {
        QUI\System\Log::writeDebugException($Exception);
    }
}


$Registration = new QUI\FrontendUsers\Controls\RegistrationSignUp([
    'content'    => $Site->getAttribute('content'),
    'registrars' => $registrars
]);

if (QUI::getPackageManager()->isInstalled('quiqqer/registration-trial')) {
    $Registration->setAttribute('registration-trial', true);
}


$Engine->assign([
    'Registration' => $Registration,
    'Background'   => $Background,
    'Logo'         => $Site->getProject()->getMedia()->getLogoImage()
]);


/**
 * Links
 */

// Privacy
$result = $Project->getSites([
    'where' => [
        'type' => 'quiqqer/sitetypes:types/privacypolicy'
    ],
    'limit' => 1
]);

if (isset($result[0])) {
    $Engine->assign('Site_Privacy', $result[0]);
}

// AGB
$result = $Project->getSites([
    'where' => [
        'type' => 'quiqqer/sitetypes:types/generalTermsAndConditions'
    ],
    'limit' => 1
]);


if (isset($result[0])) {
    $Engine->assign('Site_TermsAndConditions', $result[0]);
}

// Legal Notes
$result = $Project->getSites([
    'where' => [
        'type' => 'quiqqer/sitetypes:types/legalnotes'
    ],
    'limit' => 1
]);


if (isset($result[0])) {
    $Engine->assign('Site_LegalNotes', $result[0]);
}
