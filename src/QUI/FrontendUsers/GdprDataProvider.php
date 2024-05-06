<?php

namespace QUI\FrontendUsers;

use QUI\GDPR\DataRequest\AbstractDataProvider;

/**
 * Class QuiqqerUserDataProvider
 *
 * GDPR provider for QUIQQER frontend-users data
 */
class GdprDataProvider extends AbstractDataProvider
{
    /**
     * Get general title of the data section / provider.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->Locale->get(
            'quiqqer/frontend-users',
            'GdprDataProvider.title'
        );
    }

    /**
     * Does this GDPR data provider have any data of the user?
     *
     * @return bool
     */
    public function hasUserData(): bool
    {
        // Fetch registrar
        $registrarClass = $this->User->getAttribute('quiqqer.frontendUsers.registrar');

        // If user has no registrar this means he did not register via frontend registration
        return $registrarClass !== false;
    }

    /**
     * Get description of the purpose (=reason why) the concrete user data is
     * used by this provider.
     *
     * @return string
     */
    public function getPurpose(): string
    {
        return $this->Locale->get(
            'quiqqer/frontend-users',
            'GdprDataProvider.purpose'
        );
    }

    /**
     * Get list of recipients of the user data.
     *
     * @return string
     */
    public function getRecipients(): string
    {
        return $this->Locale->get(
            'quiqqer/frontend-users',
            'GdprDataProvider.recipients'
        );
    }

    /**
     * Get description of the storage duration of the user data.
     *
     * If no concrete duration is available, the criteria for the storage duration shall be provided.
     *
     * @return string
     */
    public function getStorageDuration(): string
    {
        return $this->Locale->get(
            'quiqqer/frontend-users',
            'GdprDataProvider.storageDuration'
        );
    }

    /**
     * Get description of the origin of the data.
     *
     * @return string
     */
    public function getOrigin(): string
    {
        // Fetch registrar
        $registrarClass = $this->User->getAttribute('quiqqer.frontendUsers.registrar');

        if (\class_exists($registrarClass)) {
            /** @var AbstractRegistrar $Registrar */
            $Registrar = new $registrarClass();
            $registrarTitle = $Registrar->getTitle($this->Locale);
        } else {
            $registrarTitle = $this->Locale->get(
                'quiqqer/frontend-users',
                'GdprDataProvider.origin.defaultRegistrarTitle'
            );
        }

        return $this->Locale->get(
            'quiqqer/frontend-users',
            'GdprDataProvider.origin',
            [
                'registrarTitle' => $registrarTitle,
                'registrationDate' => $this->Locale->formatDate((int)$this->User->getAttribute('regdate'))
            ]
        );
    }

    /**
     * Custom text for individual text relevant to GDPR data requests.
     *
     * @return string
     */
    public function getCustomText(): string
    {
        return '';
    }

    /**
     * Get all individual user data fields this provider has saved of the user.
     *
     * @return array - Key is title, value is concrete user data value
     */
    public function getUserDataFields(): array
    {
        $lg = 'quiqqer/frontend-users';
        $prefix = 'GdprDataProvider.userDataField.';

        $dataFields = [
            $this->Locale->get($lg, $prefix . 'userId') => $this->User->getUUID(),
            $this->Locale->get($lg, $prefix . 'email') => $this->User->getAttribute('email')
        ];

        $firstname = $this->User->getAttribute('firstname');
        $lastname = $this->User->getAttribute('lastname');

        if (!empty($firstname)) {
            $dataFields[$this->Locale->get($lg, $prefix . 'firstName')] = $firstname;
        }

        if (!empty($lastname)) {
            $dataFields[$this->Locale->get($lg, $prefix . 'lastName')] = $lastname;
        }

        return $dataFields;
    }

    /**
     * Delete all user data this provider has saved.
     *
     * Only has to delete GDPR relevant user data and user data that is not required
     * to be kept for legal purposes (e.g. invoice, tax etc.).
     *
     * @return string[] - List of data fields that were deleted.
     */
    public function deleteUserData(): array
    {
        $this->User->disable(); // anonymize user

        return [
            'username',
            'firstname',
            'lastname',
            'birthday',
            'email'
        ];
    }
}
