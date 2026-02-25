<?php

/**
 * Checks if the current session user is allowed to delete the user account
 *
 * @return void
 * @throws \Exception
 */

use QUI\FrontendUsers\Controls\Profile\DeleteAccount;

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_checkDeleteAccount',
    function () {
        DeleteAccount::checkDeleteAccount();
    },
    [],
    ['Permission::checkUser']
);
