<?php

namespace QUI\GDPR\DataRequest;

use QUI\Interfaces\Users\User as UserInterface;
use QUI\Locale as QUILocale;

/**
 * Class AbstractDataProvider
 *
 * Base abstract class for all GDPR data providers.
 */
abstract class AbstractDataProvider implements DataProviderInterface
{
    protected UserInterface $User;
    protected QUILocale     $Locale;

    /**
     * DataProviderInterface constructor.
     *
     * @param UserInterface $User - The user from whom data is provided
     * @param QUILocale|null $Locale (optional) - The Locale (language) the data is provided in [default: language of $User]
     */
    public function __construct(UserInterface $User, ?QUILocale $Locale = null)
    {
        $this->User = $User;

        if (empty($Locale)) {
            $Locale = $User->getLocale();
        }

        /** @var QUILocale $Locale */
        $this->Locale = $Locale;
    }

    /**
     * Does this GDPR data provider have any data of the user?
     *
     * @return bool
     */
    abstract public function hasUserData(): bool;

    /**
     * Get general title of the data section / provider.
     *
     * @return string
     */
    abstract public function getTitle(): string;

    /**
     * Get description of the purpose (=reason why) the concrete user data is
     * used by this provider.
     *
     * @return string
     */
    abstract public function getPurpose(): string;

    /**
     * Get list of recipients of the user data.
     *
     * @return string
     */
    abstract public function getRecipients(): string;

    /**
     * Get description of the storage duration of the user data.
     *
     * If no concrete duration is available, the criteria for the storage duration shall be provided.
     *
     * @return string
     */
    abstract public function getStorageDuration(): string;

    /**
     * Get description of the origin of the data.
     *
     * @return string
     */
    abstract public function getOrigin(): string;

    /**
     * Custom text for individual text relevant to GDPR data requests.
     *
     * @return string
     */
    abstract public function getCustomText(): string;

    /**
     * Get all individual user data fields this provider has saved of the user.
     *
     * @return array - Key is title, value is concrete user data value
     */
    abstract public function getUserDataFields(): array;

    /**
     * Delete all user data this provider has saved.
     *
     * Only has to delete GDPR relevant user data and user data that is not required
     * to be kept for legal purposes (e.g. invoice, tax etc.).
     *
     * @return string[] - List of data fields that were deleted.
     */
    abstract public function deleteUserData(): array;
}