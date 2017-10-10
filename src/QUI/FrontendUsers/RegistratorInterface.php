<?php

/**
 * This file contains \QUI\FrontendUsers\RegistratorInterface
 */

namespace QUI\FrontendUsers;

use QUI;

/**
 * Interface RegistratorInterface
 *
 * @package QUI\FrontendUsers
 */
interface RegistratorInterface
{
    //region attributes

    /**
     * Set registration data
     *
     * @param array $attributes
     */
    public function setAttributes($attributes);

    /**
     * Return the sent registrator data
     *
     * @return array
     */
    public function getAttributes();

    /**
     * Set one attribute to the registrator
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function setAttribute($key, $value);

    /**
     * Return a attribute
     *
     * @param string $key - name of the attribute
     * @return mixed
     */
    public function getAttribute($key);

    //endregion attributes
    /**
     * @param QUI\Interfaces\Users\User $User
     * @return int - QUI\FrontendUsers\Handler::REGISTRATION_STATUS_*
     */
    public function onRegistered(QUI\Interfaces\Users\User $User);

    /**
     * Create a new user
     *
     * @return QUI\Users\User
     * @throws Exception
     */
    public function createUser();

    /**
     * Validate registration data
     *
     * @throws Exception
     */
    public function validate();

    /**
     * Return the username which is to be registered
     *
     * @throws Exception
     */
    public function getUsername();

    /**
     * Return the control for the registrator
     *
     * @return \QUI\Control
     */
    public function getControl();

    /**
     * Get title
     *
     * @param QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    public function getTitle($Locale = null);

    /**
     * Get description
     *
     * @param QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    public function getDescription($Locale = null);
}
