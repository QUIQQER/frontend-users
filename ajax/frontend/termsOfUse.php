<?php

QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_termsOfUse',
    function () {
        return QUI::getPackage('quiqqer/frontend-users')
            ->getConfig()
            ->get('registration', 'termsOfUseRequired');
    }
);
