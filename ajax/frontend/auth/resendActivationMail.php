<?php

/**
 * This file contains package_quiqqer_frontend-users_ajax_frontend_auth_resendActivationMail
 */

use QUI\FrontendUsers\ActivationResend;

/**
 * Resend an activation mail
 *
 * @return bool - request acknowledged; does not disclose account or delivery state
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_auth_resendActivationMail',
    function ($email) {
        try {
            if (is_string($email)) {
                ActivationResend::request($email);
            }
        } catch (Throwable $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return true;
    },
    ['email']
);
