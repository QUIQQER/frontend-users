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
        try {
            $QueryBuilder = QUI::getDataBaseConnection()->createQueryBuilder();
            $result = $QueryBuilder
                ->select(QUI\Utils\Doctrine::quoteIdentifier('id'))
                ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName('users')))
                ->where(QUI\Utils\Doctrine::quoteIdentifier('active') . ' = :active')
                ->setParameter('active', 0)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Doctrine\DBAL\Exception $Exception) {
            throw new Exception(
                $Exception->getMessage(),
                (int)$Exception->getCode()
            );
        }

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
