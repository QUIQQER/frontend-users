<?php

/**
 * return the sign-up registration url
 *
 * @return string
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_registrars_getRegistrationLink',
    function ($project) {
        $Project = QUI\Projects\Manager::decode($project);

        $types = [
            'quiqqer/frontend-users:types/registrationSignUp',
            'quiqqer/frontend-users:types/registration',
        ];

        $registerSite = $Project->getSites([
            'where' => [
                'type' => [
                    'type' => 'IN',
                    'value' => $types
                ]
            ],
            'limit' => 1
        ]);


        if (count($registerSite)) {
            return $registerSite[0]->getUrlRewritten();
        }

        return '';
    },
    ['project']
);
