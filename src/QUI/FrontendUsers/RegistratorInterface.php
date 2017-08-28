<?php

/**
 * This file contains \QUI\FrontendUsers\RegistratorInterface
 */

namespace QUI\FrontendUsers;

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
     * @param array $userData
     */
    public function setAttributes(array $userData);

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
}
