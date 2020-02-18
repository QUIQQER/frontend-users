<?php

/**
 * This file contains QUI\FrontendUsers\Registrars\Email\Registrar
 */

namespace QUI\FrontendUsers\Registrars\Email;

use QUI;
use QUI\FrontendUsers;
use QUI\FrontendUsers\InvalidFormField;
use QUI\Utils\Security\Orthos;
use QUI\Captcha\Handler as CaptchaHandler;

/**
 * Class Email\Registrar
 *
 * Registration via e-mail address
 *
 * @package QUI\FrontendUsers\Registrars
 */
class Registrar extends FrontendUsers\AbstractRegistrar
{
    /**
     * @param QUI\Interfaces\Users\User $User
     * @return void
     */
    public function onRegistered(QUI\Interfaces\Users\User $User)
    {
        $SystemUser           = QUI::getUsers()->getSystemUser();
        $RegistrarHandler     = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();
        $useAddress           = boolval($registrationSettings['addressInput']);

        /** @var QUI\Users\User $User */
        $firstname = $this->getAttribute('firstname');
        $lastname  = $this->getAttribute('lastname');

        if (!empty($firstname)) {
            $User->setAttribute('firstname', $firstname);
        }

        if (!empty($lastname)) {
            $User->setAttribute('lastname', $lastname);
        }

        // set e-mail address
        $User->setAttribute('email', $this->getAttribute('email'));

        // set address data
        if ($useAddress) {
            $UserAddress = $User->addAddress([
                'salutation' => $this->getAttribute('salutation'),
                'firstname'  => $this->getAttribute('firstname'),
                'lastname'   => $this->getAttribute('lastname'),
                'mail'       => $this->getAttribute('email'),
                'company'    => $this->getAttribute('company'),
                'street_no'  => $this->getAttribute('street_no'),
                'zip'        => $this->getAttribute('zip'),
                'city'       => $this->getAttribute('city'),
                'country'    => mb_strtolower($this->getAttribute('country'))
            ], $SystemUser);

            $User->setAttributes([
                'firstname' => $this->getAttribute('firstname'),
                'lastname'  => $this->getAttribute('lastname'),
                'address'   => $UserAddress->getId()    // set as main address
            ]);

            $tel    = $this->getAttribute('phone');
            $mobile = $this->getAttribute('mobile');
            $fax    = $this->getAttribute('fax');

            if (!empty($tel)) {
                $UserAddress->addPhone([
                    'type' => 'tel',
                    'no'   => $tel
                ]);
            }

            if (!empty($mobile)) {
                $UserAddress->addPhone([
                    'type' => 'mobile',
                    'no'   => $mobile
                ]);
            }

            if (!empty($fax)) {
                $UserAddress->addPhone([
                    'type' => 'fax',
                    'no'   => $fax
                ]);
            }

            $UserAddress->save($SystemUser);
        }

        if ($this->getAttribute('password')) {
            $User->setPassword($this->getAttribute('password'), $SystemUser);
        }

        $User->save($SystemUser);
    }

    /**
     * @throws FrontendUsers\Exception
     */
    public function validate()
    {
        $username       = $this->getUsername();
        $Handler        = FrontendUsers\Handler::getInstance();
        $settings       = $Handler->getRegistrationSettings();
        $usernameInput  = $settings['usernameInput'];
        $fullnameInput  = $settings['fullnameInput'];
        $usernameExists = QUI::getUsers()->usernameExists($username);

        $lg       = 'quiqqer/frontend-users';
        $lgPrefix = 'exception.registrars.email.';

        // Username check
        if ($usernameInput !== $Handler::USERNAME_INPUT_NONE) {
            // Check if username input is enabled
            if (empty($username)
                && $usernameInput === $Handler::USERNAME_INPUT_REQUIRED) {
                throw new FrontendUsers\Exception([
                    $lg,
                    $lgPrefix.'empty_username'
                ]);
            }

            if ($usernameExists) {
                throw new FrontendUsers\Exception([
                    $lg,
                    $lgPrefix.'username_already_exists'
                ]);
            }
        } else {
            // Check if username input is not enabled
            if ($usernameExists) {
                throw new FrontendUsers\Exception([
                    $lg,
                    $lgPrefix.'email_already_exists'
                ]);
            }
        }

        // Fullname check
        $firstname = $this->getAttribute('firstname');
        $lastname  = $this->getAttribute('lastname');

        switch ($fullnameInput) {
            case $Handler::FULLNAME_INPUT_FIRSTNAME_REQUIRED:
                if (empty($firstname)) {
                    throw new FrontendUsers\Exception([$lg, $lgPrefix.'first_name_required']);
                }
                break;

            case $Handler::FULLNAME_INPUT_FULLNAME_REQUIRED:
                if (empty($firstname) || empty($lastname)) {
                    throw new FrontendUsers\Exception([$lg, $lgPrefix.'full_name_required']);
                }
        }

        try {
            QUI::getUsers()->getUserByName($username);

            // Username already exists
            throw new FrontendUsers\Exception([
                $lg,
                $lgPrefix.'username_already_exists'
            ]);
        } catch (\Exception $Exception) {
            // Username does not exist
        }

        $email = $this->getAttribute('email');

        if (QUI::getUsers()->emailExists($email)) {
            throw new FrontendUsers\Exception([
                $lg,
                $lgPrefix.'email_already_exists'
            ]);
        }

        if (!Orthos::checkMailSyntax($email)) {
            throw new FrontendUsers\Exception([
                $lg,
                $lgPrefix.'email_invalid'
            ]);
        }

        // Address validation
        if ((int)$settings['addressInput']) {
            foreach ($Handler->getAddressFieldSettings() as $field => $addressSettings) {
                $val = $this->getAttribute($field);

                if ($addressSettings['required'] && empty($val)) {
                    throw new FrontendUsers\Exception([
                        $lg,
                        $lgPrefix.'missing_address_fields'
                    ]);
                }
            }
        }

        // CAPTCHA validation
        if (boolval($settings['useCaptcha'])) {
            $captchaResponse = $this->getAttribute('captchaResponse');

            if (empty($captchaResponse)) {
                throw new FrontendUsers\Exception([
                    $lg,
                    $lgPrefix.'captcha_empty'
                ]);
            }

            if (!CaptchaHandler::isResponseValid($captchaResponse)) {
                throw new FrontendUsers\Exception([
                    $lg,
                    $lgPrefix.'captcha_invalid_response'
                ]);
            }
        }
    }

    /**
     * Get all invalid registration form fields
     *
     * @return InvalidFormField[]
     */
    public function getInvalidFields()
    {
        $username         = $this->getUsername();
        $invalidFields    = [];
        $RegistrarHandler = FrontendUsers\Handler::getInstance();
        $settings         = $RegistrarHandler->getRegistrationSettings();
        $usernameInput    = $settings['usernameInput'];
        $Users            = QUI::getUsers();
        $usernameExists   = $Users->usernameExists($username);

        $L        = QUI::getLocale();
        $lg       = 'quiqqer/frontend-users';
        $lgPrefix = 'exception.registrars.email.';

        // Username check
        if ($usernameInput !== $RegistrarHandler::USERNAME_INPUT_NONE) {
            // Check if username input is enabled
            if (empty($username)
                && $usernameInput === $RegistrarHandler::USERNAME_INPUT_REQUIRED) {
                $invalidFields['username'] = new InvalidFormField(
                    'username',
                    $L->get($lg, $lgPrefix.'empty_username')
                );
            }

            if ($usernameExists) {
                $invalidFields['username'] = new InvalidFormField(
                    'username',
                    $L->get($lg, $lgPrefix.'username_already_exists')
                );
            }
        } else {
            // Check if username input is not enabled
            if ($usernameExists) {
                $invalidFields['email'] = new InvalidFormField(
                    'email',
                    $L->get($lg, $lgPrefix.'email_already_exists')
                );
            }
        }

        // Email check
        $email = $this->getAttribute('email');

        if (empty($email)) {
            $invalidFields['email'] = new InvalidFormField(
                'email',
                $L->get($lg, $lgPrefix.'empty_email')
            );
        }

        if ($Users->emailExists($email)) {
            $invalidFields['email'] = new InvalidFormField(
                'email',
                $L->get($lg, $lgPrefix.'email_already_exists')
            );
        }

        if (!Orthos::checkMailSyntax($email)) {
            $invalidFields['email'] = new InvalidFormField(
                'email',
                $L->get($lg, $lgPrefix.'email_invalid')
            );
        }

        // Address validation
        if ((int)$settings['addressInput']) {
            foreach ($RegistrarHandler->getAddressFieldSettings() as $field => $settings) {
                $val = $this->getAttribute($field);

                if ($settings['required'] && empty($val)) {
                    $invalidFields[$field] = new InvalidFormField(
                        $field,
                        $L->get($lg, $lgPrefix.'missing_field')
                    );
                }
            }
        }

        return $invalidFields;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        $data = $this->getAttributes();

        if (!empty($data['username'])) {
            return $data['username'];
        }

        if (!empty($data['email'])) {
            return $data['email'];
        }

        return '';
    }

    /**
     * @return Control
     */
    public function getControl()
    {
        $invalidFields = [];

        if (!empty($_POST['registration'])
            && !empty($_POST['registrar'])
            && $_POST['registrar'] === $this->getHash()) {
            $invalidFields = $this->getInvalidFields();
        }

        return new Control([
            'invalidFields' => $invalidFields
        ]);
    }

    /**
     * Get title
     *
     * @param QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if (is_null($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/frontend-users', 'registrar.email.title');
    }

    /**
     * Get description
     *
     * @param QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    public function getDescription($Locale = null)
    {
        if (is_null($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/frontend-users', 'registrar.email.description');
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa fa-envelope';
    }

    /**
     * Check if this Registrar can send passwords
     *
     * @return bool
     * @throws QUI\Exception
     */
    public function canSendPassword()
    {
        // Can only send password if the user does not provide it himself
        $registrationSettings = FrontendUsers\Handler::getInstance()->getRegistrationSettings();

        return $registrationSettings['passwordInput'] === 'none';
    }
}
