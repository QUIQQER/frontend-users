<?php

/**
 * @return string - User e-mail address
 */

use QUI\Verification\VerificationRepository;

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_auth_existsUnverifiedActivation',
    function ($userId) {
        try {
            $User = QUI::getUsers()->get($userId);
            $verificationRepository = new VerificationRepository();
            $verification = $verificationRepository->findByIdentifier(
                'activate-' . $User->getUUID()
            );
        } catch (Exception) {
            return false;
        }

        return $verification ? $User->getAttribute('email') : false;
    },
    ['userId']
);
