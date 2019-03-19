<?php

use QUI\FrontendUsers\Controls\Profile\DeleteAccount;

/**
 * Checks if the current session user is allowed to delete the user account
 *
 * @return void
 * @throws \Exception
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_profile_checkDeleteAccount',
    function () {
        DeleteAccount::checkDeleteAccount();
    },
    [],
    ['Permission::checkUser']
);
