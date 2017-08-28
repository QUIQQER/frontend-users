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
     * Create a new user
     *
     * @throws Exception
     */
    public function createUser()
    {
        $this->validate();

        // start the registration
        $Users = QUI::getUsers();
        $User  = $Users->createChild($this->getUsername());

        $status = $this->onRegistered($User);
    }
}
