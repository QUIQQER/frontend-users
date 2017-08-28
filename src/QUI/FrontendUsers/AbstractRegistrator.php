<?php

/**
 * Namespace QUI\FrontendUsers\AbstractRegistrar
 */

namespace QUI\FrontendUsers;

use QUI;

/**
 * Class AbstractRegistrar
 *
 * @package QUI\FrontendUsers
 */
abstract class AbstractRegistrator extends QUI\QDOM implements RegistratorInterface
{
    /**
     * @return mixed
     */
    abstract public function validate();

    /**
     * @return mixed
     */
    abstract public function getUsername();

    /**
     * @return mixed
     */
    abstract public function getControl();

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return integer
     */
    abstract public function onRegistered(QUI\Interfaces\Users\User $User);

    /**
     * Return the success message
     * @return string
     */
    public function getSuccessMessage()
    {
        return QUI::getLocale()->get('quiqqer/frontend-users', 'message.successfully.registered');
    }

    /**
     * @return string
     */
    public function getPendingMessage()
    {
        return '';
    }

    /**
     * Create a new user
     *
     * @return int - QUI\FrontendUsers\Handler::REGISTRATION_STATUS_*
     * @throws Exception
     */
    public function createUser()
    {
        $this->validate();

        // start the registration
        $Users = QUI::getUsers();

        $User = $Users->createChild(
            $this->getUsername(),
            QUI::getUsers()->getSystemUser()
        );

        return $this->onRegistered($User);
    }
}
