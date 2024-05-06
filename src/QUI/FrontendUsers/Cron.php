<?php

namespace QUI\FrontendUsers;

use DateTime;
use QUI;
use QUI\Database\Exception;
use QUI\ExceptionStack;

class Cron
{
    /**
     * Delete users that registered via frontend and have not been
     * activated yet after X days
     *
     * @return void
     * @throws Exception
     * @throws QUI\Exception
     * @throws ExceptionStack
     * @throws QUI\Permissions\Exception
     * @throws \Exception
     */
    public static function deleteUnverifiedInactiveUsers(): void
    {
        $result = QUI::getDataBase()->fetch([
            'select' => [
                'id'
            ],
            'from' => QUI::getDBTableName('users'),
            'where' => [
                'active' => 0
            ]
        ]);

        $Users = QUI::getUsers();
        $Handler = Handler::getInstance();
        $settings = $Handler->getRegistrationSettings();
        $maxInactiveDays = (int)$settings['deleteInactiveUserAfterDays'];
        $Now = new DateTime();

        foreach ($result as $row) {
            $User = $Users->get($row['id']);

            // do not check if user was created/deactivated via user administration in backend
            if (!$User->getAttribute($Handler::USER_ATTR_USER_ACTIVATION_REQUIRED)) {
                continue;
            }

            $RegistrationDate = new DateTime("@" . $User->getAttribute('regdate'));

            if ($Now->diff($RegistrationDate)->days > $maxInactiveDays) {
                $User->delete();
            }
        }
    }
}
