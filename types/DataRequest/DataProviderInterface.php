<?php

namespace QUI\GDPR\DataRequest;

use QUI\Interfaces\Users\User as UserInterface;
use QUI\Locale as QUILocale;

/**
 * Interface DataProviderInterface
 *
 * Interface for all GDPR data providers.
 */
interface DataProviderInterface
{
    /**
     * DataProviderInterface constructor.
     *
     * @param UserInterface $User - The user from whom data is provided
     * @param QUILocale|null $Locale (optional) - The Locale (language) the data is provided in [default: language of $User]
     */
    public function __construct(UserInterface $User, ?QUILocale $Locale = null);

    // region Data request

    /**
     * Does this GDPR data provider have any data of the user?
     *
     * @return bool
     */
    public function hasUserData(): bool;

    /**
     * Get general title of the data section / provider.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Get description of the purpose (=reason why) the concrete user data is
     * used by this provider.
     *
     * @return string
     */
    public function getPurpose(): string;

    /**
     * Get recipients of the user data.
     *
     * @return string
     */
    public function getRecipients(): string;

    /**
     * Get description of the storage duration of the user data.
     *
     * If no concrete duration is available, the criteria for the storage duration shall be provided.
     *
     * @return string
     */
    public function getStorageDuration(): string;

    /**
     * Get description of the origin of the data.
     *
     * @return string
     */
    public function getOrigin(): string;

    /**
     * Custom text for individual text relevant to GDPR data requests.
     *
     * @return string
     */
    public function getCustomText(): string;

    /**
     * Get all individual user data fields this provider has saved of the user.
     *
     * @return array - Key is title, value is concrete user data value
     */
    public function getUserDataFields(): array;

    // endregion

    // region Data erasure

    /**
     * Delete all user data this provider has saved.
     *
     * Only has to delete GDPR relevant user data and user data that is not required
     * to be kept for legal purposes (e.g. invoice, tax etc.).
     *
     * @return string[] - List of data fields that were deleted.
     */
    public function deleteUserData(): array;

    // endregion
}