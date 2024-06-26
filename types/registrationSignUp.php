<?php

/**
 * This file contains the registration signup site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

use QUI\Utils\Security\Orthos;

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

/**
 * Registration / Sign up
 */
$Registration = new QUI\FrontendUsers\Controls\RegistrationSignUp([
    'content' => $Site->getAttribute('content'),
    'registrars' => $registrars
]);

if (QUI::getPackageManager()->isInstalled('quiqqer/registration-trial')) {
    $Registration->setAttribute('registration-trial', true);
}

// logo url
$logoUrl = $Project->firstChild()->getUrlRewritten();

if ($Site->getAttribute('quiqqer.sign.up.logoUrl')) {
    $siteUrl = $Site->getAttribute('quiqqer.sign.up.logoUrl');

    if (QUI\Projects\Site\Utils::isSiteLink($siteUrl)) {
        try {
            $InternalSite = QUI\Projects\Site\Utils::getSiteByLink($siteUrl);
            $logoUrl = $InternalSite->getUrlRewritten();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    } else {
        $siteUrl = Orthos::clearFormRequest($siteUrl);
        $logoUrl = $siteUrl;
    }
}

// logo
$Logo = null;

if ($Site->getAttribute('quiqqer.sign.up.logo')) {
    try {
        $Logo = QUI\Projects\Media\Utils::getImageByUrl(
            $Site->getAttribute('quiqqer.sign.up.logo')
        );
    } catch (QUI\Exception $Exception) {
        QUI\System\Log::writeDebugException($Exception);
    }
}

if (!$Logo) {
    $Logo = $Site->getProject()->getMedia()->getLogoImage();
}

$Engine->assign([
    'Registration' => $Registration,
    'Background' => $Background,
    'Logo' => $Logo,
    'logoUrl' => $logoUrl,
    'fullscreen' => !!$Site->getAttribute('quiqqer.sign.up.fullscreen')
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
