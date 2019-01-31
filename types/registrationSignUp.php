<?php

$background = $Site->getAttribute('quiqqer.sign.up.background');
$Background = null;

if (QUI\Projects\Media\Utils::isMediaUrl($background)) {
    try {
        $Background = QUI\Projects\Media\Utils::getImageByUrl($background);
    } catch (QUI\Exception $exception) {
    }
}

$Registration = new QUI\FrontendUsers\Controls\RegistrationSignUp([
    'content' => $Site->getAttribute('content')
]);

if (QUI::getPackageManager()->isInstalled('quiqqer/registration-trial')) {
    $Registration->setAttribute('registration-trial', true);
}

$Engine->assign([
    'Registration' => $Registration,
    'Background'   => $Background
]);
