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
     * Get title
     *
     * @param QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    abstract public function getTitle($Locale = null);

    /**
     * Get description
     *
     * @param QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    abstract public function getDescription($Locale = null);

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
     * @return QUI\Users\User
     * @throws Exception
     */
    public function createUser()
    {
        return QUI::getUsers()->createChild(
            $this->getUsername(),
            QUI::getUsers()->getSystemUser()
        );
    }
}
