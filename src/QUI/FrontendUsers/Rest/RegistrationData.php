<?php

namespace QUI\FrontendUsers\Rest;

use Psr\Http\Message\ServerRequestInterface as SlimRequest;
use QUI;
use QUI\FrontendUsers\Exception;
use QUI\FrontendUsers\Handler;
use QUI\QDOM;

class RegistrationData extends QDOM
{
    /**
     * Returns an array where the keys are the required fields.
     * The values contain arrays with further information about the fields requirements.
     * For example the maximum allowed length for a field.
     *
     * @return array
     *
     * @throws \QUI\Exception
     */
    public static function getRequiredFields(): array
    {
        $requiredFields = [];

        $Handler = Handler::getInstance();

        $registrationSettings = $Handler->getRegistrationSettings();

        if ($registrationSettings['usernameInput'] !== $Handler::USERNAME_INPUT_NONE) {
            $requiredFields[] = 'username';
        }

        if ($registrationSettings['passwordInput'] != $Handler::PASSWORD_INPUT_NONE) {
            $requiredFields[] = 'password';
        }

        if ($registrationSettings['fullnameInput'] == $Handler::FULLNAME_INPUT_FIRSTNAME_REQUIRED) {
            $requiredFields[] = 'firstname';
        }

        if ($registrationSettings['fullnameInput'] == $Handler::FULLNAME_INPUT_FULLNAME_REQUIRED) {
            $requiredFields[] = 'firstname';
            $requiredFields[] = 'lastname';
        }

        $requiredFields[] = 'email';

        if ((int)$registrationSettings['addressInput']) {
            foreach ($Handler->getAddressFieldSettings() as $fieldName => $fieldSettings) {
                if ($fieldSettings['required']) {
                    $requiredFields[] = $fieldName;
                }
            }
        }

        $userAttributeLengthRestrictions = Handler::getInstance()->getUserAttributeLengthRestrictions();

        $result = [];
        foreach ($requiredFields as $requiredField) {
            $maxLength = null;

            if (isset($userAttributeLengthRestrictions[$requiredField])) {
                $maxLength = $userAttributeLengthRestrictions[$requiredField];
            }

            $result[$requiredField] = [
                'max_length' => $maxLength
            ];
        }

        return $result;
    }

    /**
     * Creates a RegistrationData object from a given Request
     *
     * @param \Psr\Http\Message\ServerRequestInterface $Request
     *
     * @return \QUI\FrontendUsers\Rest\RegistrationData
     *
     * @throws \QUI\Exception
     */
    public static function buildFromRequest(SlimRequest $Request): RegistrationData
    {
        $RegistrationData = new static();
        $RegistrationData->setAttributes($Request->getParsedBody());

        $Handler = QUI\FrontendUsers\Handler::getInstance();

        $registrationSettings = $Handler->getRegistrationSettings();

        $usernameSetting = $registrationSettings['usernameInput'];

        if ($usernameSetting === $Handler::USERNAME_INPUT_NONE) {
            $RegistrationData->setAttribute('username', $RegistrationData->getAttribute('email'));
        }

        if ($usernameSetting === $Handler::USERNAME_INPUT_OPTIONAL && !$RegistrationData->getAttribute('username')) {
            $RegistrationData->setAttribute('username', $RegistrationData->getAttribute('email'));
        }

        return $RegistrationData;
    }

    /**
     * Checks if the stored data is valid for registration.
     * An Exception will be thrown, if something is wrong.
     *
     * @return void
     *
     * @throws QUI\Exception
     * @throws QUI\FrontendUsers\Exception
     */
    public function validate(): void
    {
        $Handler = QUI\FrontendUsers\Handler::getInstance();

        $registrationSettings = $Handler->getRegistrationSettings();

        $usernameSetting = $registrationSettings['usernameInput'];
        $passwordSetting = $registrationSettings['passwordInput'];
        $fullNameSetting = $registrationSettings['fullnameInput'];

        $lg       = 'quiqqer/frontend-users';
        $lgPrefix = 'exception.registrars.email.';


        $email    = $this->getAttribute('email');

        // Email check
        if (empty($email)) {
            throw new QUI\FrontendUsers\Exception([$lg, $lgPrefix . 'empty_email']);
        }

        if (!Orthos::checkMailSyntax($email)) {
            throw new QUI\FrontendUsers\Exception([$lg, $lgPrefix . 'email_invalid']);
        }

        if (QUI::getUsers()->emailExists($email)) {
            throw new QUI\FrontendUsers\Exception([$lg, $lgPrefix . 'email_already_exists']);
        }


        $username = $this->getAttribute('username');

        // Username check
        if (\mb_strlen($username) > 50) {
            throw new QUI\FrontendUsers\Exception([
                $lg,
                'exception.registration.username_too_long',
                ['maxLength' => 50]
            ]);
        }

        if ($usernameSetting === $Handler::USERNAME_INPUT_REQUIRED) {
            if (empty($username)) {
                throw new QUI\FrontendUsers\Exception([
                    $lg,
                    $lgPrefix . 'empty_username'
                ]);
            }

            if (QUI::getUsers()->usernameExists($username)) {
                throw new QUI\FrontendUsers\Exception([
                    $lg,
                    $lgPrefix . 'username_already_exists'
                ]);
            }
        }

        // Password check
        if ($passwordSetting != $Handler::PASSWORD_INPUT_NONE && !$this->getAttribute('password')) {
            throw new QUI\FrontendUsers\Exception([$lg, $lgPrefix . 'password_missing']);
        }

        // Fullname check
        $firstname = $this->getAttribute('firstname');
        $lastname  = $this->getAttribute('lastname');

        switch ($fullNameSetting) {
            case $Handler::FULLNAME_INPUT_FIRSTNAME_REQUIRED:
                if (empty($firstname)) {
                    throw new QUI\FrontendUsers\Exception([$lg, $lgPrefix . 'first_name_required']);
                }
                break;

            case $Handler::FULLNAME_INPUT_FULLNAME_REQUIRED:
                if (empty($firstname) || empty($lastname)) {
                    throw new QUI\FrontendUsers\Exception([$lg, $lgPrefix . 'full_name_required']);
                }
        }

        try {
            QUI::getUsers()->getUserByName($username);

            // Username already exists
            throw new QUI\FrontendUsers\Exception([
                $lg,
                $lgPrefix . 'username_already_exists'
            ]);
        } catch (\Exception $Exception) {
            // Username does not exist
        }

        // Address validation
        if ((int)$registrationSettings['addressInput']) {
            foreach ($Handler->getAddressFieldSettings() as $field => $addressSettings) {
                $val = $this->getAttribute($field);

                if ($addressSettings['required'] && empty($val)) {
                    throw new QUI\FrontendUsers\Exception([
                        $lg,
                        $lgPrefix . 'missing_address_fields'
                    ]);
                }
            }
        }

        // Length check
        foreach ($Handler->getUserAttributeLengthRestrictions() as $attribute => $maxLength) {
            $value = $this->getAttribute($attribute);

            if (empty($value)) {
                continue;
            }

            if (\mb_strlen($value) > $maxLength) {
                throw new Exception([
                    'quiqqer/frontend-users',
                    'exception.registrars.email.user_attribute_too_long',
                    [
                        'label'     => QUI::getLocale()->get('quiqqer/system', $attribute),
                        'maxLength' => $maxLength
                    ]
                ]);
            }
        }
    }
}
