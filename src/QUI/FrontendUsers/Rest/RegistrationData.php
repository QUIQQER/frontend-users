<?php

namespace QUI\FrontendUsers\Rest;

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
}
