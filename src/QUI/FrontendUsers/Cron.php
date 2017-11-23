<?php

namespace QUI\FrontendUsers;

use QUI;

class Cron
{
    /**
     * Delete users that registered via frontend and have not been
     * activated yet after X days
     *
     * @return void
     */
    public static function deleteUnverifiedInactiveUsers()
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => QUI::getDBTableName('users'),
            'where'  => array(
                'active' => 0
            )
        ));

        $Users           = QUI::getUsers();
        $Handler         = Handler::getInstance();
        $settings        = $Handler->getRegistrationSettings();
        $maxInactiveDays = (int)$settings['deleteInactiveUserAfterDays'];
        $Now             = new \DateTime();

        foreach ($result as $row) {
            $User = $Users->get($row['id']);

            // check if user registered via frontend (and was not created by an admin)
            if (!$User->getAttribute($Handler::USER_ATTR_USER_ACTIVATION_REQUIRED)) {
                continue;
            }

            $RegistrationDate = new \DateTime($User->getAttribute('regdate'));

            if ($Now->diff($RegistrationDate)->days > $maxInactiveDays) {
                $User->delete();
            }
        }
    }
}
