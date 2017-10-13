<?php

/**
 * This file contains \QUI\FrontendUsers\RegistrarInterface
 */

namespace QUI\FrontendUsers;

use QUI;

/**
 * Interface RegistrarInterface
 *
 * @package QUI\FrontendUsers
 */
interface RegistrarInterface
{
    //region attributes

    /**
     * Set registration data
     *
     * @param array $attributes
     */
    public function setAttributes($attributes);

    /**
     * Return the sent registrar data
     *
     * @return array
     */
    public function getAttributes();

    /**
     * Set one attribute to the registrar
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
     * Return the control for the registrar
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

    /**
     * Set current Project the Registrar works for
     *
     * @param QUI\Projects\Project $Project
     * @return void
     */
    public function setProject(QUI\Projects\Project $Project);

    /**
     * Get current Project the Registrar works for
     *
     * @return QUI\Projects\Project|null
     */
    public function getProject();

    /**
     * Return the success message
     * @return string
     */
    public function getSuccessMessage();

    /**
     * Return message for pending registration status
     * @return string
     */
    public function getPendingMessage();

    /**
     * Get message for registration errors
     * @return string
     */
    public function getErrorMessage();

    /**
     * Get unique hash that identifies the Registrar
     *
     * @return string
     */
    public function getHash();

    /**
     * Returns fully qualified Namespace of this Registrar
     *
     * @return string
     */
    public function getType();
}
