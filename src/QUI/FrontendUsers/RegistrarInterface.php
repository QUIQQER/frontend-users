<?php

/**
 * This file contains \QUI\FrontendUsers\RegistrarInterface
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\Control;

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
    public function setAttributes(array $attributes);

    /**
     * Return the send registrar data
     *
     * @return array
     */
    public function getAttributes(): array;

    /**
     * Set one attribute to the registrar
     *
     * @param string $key
     * @param mixed $value
     */
    public function setAttribute(string $key, mixed $value);

    /**
     * Return an attribute
     *
     * @param string $key - name of the attribute
     * @return mixed
     */
    public function getAttribute(string $key): mixed;

    //endregion attributes

    /**
     * @param QUI\Interfaces\Users\User $User
     */
    public function onRegistered(QUI\Interfaces\Users\User $User): void;

    /**
     * Create a new user
     *
     * @return QUI\Interfaces\Users\User
     * @throws Exception
     */
    public function createUser(): QUI\Interfaces\Users\User;

    /**
     * Validate registration data
     *
     * @throws Exception
     */
    public function validate(): array;

    /**
     * Get all invalid registration form fields
     *
     * @return InvalidFormField[]
     */
    public function getInvalidFields(): array;

    /**
     * Return the username which is to be registered
     *
     * @return string
     * @throws Exception
     */
    public function getUsername(): string;

    /**
     * Return the control for the registrar
     *
     * @return Control
     */
    public function getControl(): Control;

    /**
     * Get title
     *
     * @param ?QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    public function getTitle(null | QUI\Locale $Locale = null): string;

    /**
     * Get description
     *
     * @param ?QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    public function getDescription(null | QUI\Locale $Locale = null): string;

    /**
     * Set current Project the Registrar works for
     *
     * @param QUI\Projects\Project $Project
     * @return void
     */
    public function setProject(QUI\Projects\Project $Project): void;

    /**
     * Get current Project the Registrar works for
     *
     * @return QUI\Projects\Project|null
     */
    public function getProject(): ?QUI\Projects\Project;

    /**
     * Return the success message
     * @return string
     */
    public function getSuccessMessage(): string;

    /**
     * Return message for pending registration status
     * @return string
     */
    public function getPendingMessage(): string;

    /**
     * Get message for registration errors
     * @return string
     */
    public function getErrorMessage(): string;

    /**
     * Get unique hash that identifies the Registrar
     *
     * @return string
     */
    public function getHash(): string;

    /**
     * Returns fully qualified Namespace of this Registrar
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Check if this Registrar is activated in the settings
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Check if this Registrar can send passwords
     *
     * @return bool
     */
    public function canSendPassword(): bool;

    /**
     * Validates all user attributes
     *
     * @return void
     * @throws Exception
     */
    public function checkUserAttributes(): void;
}
