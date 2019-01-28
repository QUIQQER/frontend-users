<?php

$Registration = new QUI\FrontendUsers\Controls\RegistrationSignIn([
    'content' => '<img src="https://dev.quiqqer.com/namefruits/juicer/uploads/8d4acac55b049745c9a1e5bdc9804c30/Logo-Mockup_Server_Generate_3.png" alt=""/>'
]);

if (QUI::getPackageManager()->isInstalled('quiqqer/registration-trial')) {
    $Registration->setAttribute('registration-trial', true);
}

$Engine->assign([
    'Registration' => $Registration
]);
