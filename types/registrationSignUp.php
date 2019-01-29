<?php

$Registration = new QUI\FrontendUsers\Controls\RegistrationSignUp([
    'content' => $Site->getAttribute('content')
]);

if (QUI::getPackageManager()->isInstalled('quiqqer/registration-trial')) {
    $Registration->setAttribute('registration-trial', true);
}

$Engine->assign([
    'Registration' => $Registration
]);
