<?php

/**
 * This file contains QUI\FrontendUsers\Registrars\Email\Registrar
 */

namespace QUI\FrontendUsers\Registrars\Email;

use QUI;
use QUI\FrontendUsers;
use QUI\FrontendUsers\InvalidFormField;

/**
 * Class Emai\Registrar
 *
 * Registration via e-mail address
 *
 * @package QUI\FrontendUsers\Registrars
 */
class Registrar extends FrontendUsers\AbstractRegistrar
{
    /**
     * @param QUI\Interfaces\Users\User $User
     * @return int
     */
    public function onRegistered(QUI\Interfaces\Users\User $User)
    {
        $Handler              = FrontendUsers\Handler::getInstance();
        $settings             = $this->getSettings();
        $registrationSettings = $Handler->getRegistrationSettings();
        $SystemUser           = QUI::getUsers()->getSystemUser();

        /** @var QUI\Users\User $User */

        // set e-mail address
        $User->setAttribute('email', $this->getAttribute('email'));

        // set address data
        $User->setAttributes(array(
            'firstname' => $this->getAttribute('firstname'),
            'lastname'  => $this->getAttribute('lastname')
        ));

        $UserAddress = $User->addAddress(array(
            'salutation' => $this->getAttribute('salutation'),
            'firstname'  => $this->getAttribute('firstname'),
            'lastname'   => $this->getAttribute('lastname'),
            'mail'       => $this->getAttribute('email'),
            'company'    => $this->getAttribute('company'),
            'street_no'  => $this->getAttribute('street_no'),
            'zip'        => $this->getAttribute('zip'),
            'city'       => $this->getAttribute('city'),
            'country'    => mb_strtolower($this->getAttribute('country'))
        ));

        $tel    = $this->getAttribute('phone');
        $mobile = $this->getAttribute('mobile');
        $fax    = $this->getAttribute('fax');

        if (!empty($tel)) {
            $UserAddress->addPhone(array(
                'type' => 'tel',
                'no'   => $tel
            ));
        }

        if (!empty($mobile)) {
            $UserAddress->addPhone(array(
                'type' => 'mobile',
                'no'   => $mobile
            ));
        }

        if (!empty($fax)) {
            $UserAddress->addPhone(array(
                'type' => 'fax',
                'no'   => $fax
            ));
        }

        $UserAddress->save();

        $User->setPassword($this->getAttribute('password'), $SystemUser);
        $User->save($SystemUser);

        $returnStatus = $Handler::REGISTRATION_STATUS_SUCCESS;

        switch ($settings['activationMode']) {
            case $Handler::ACTIVATION_MODE_MAIL:
                $Handler->sendActivationMail($User, $this);
                $returnStatus = $Handler::REGISTRATION_STATUS_PENDING;
                break;

            case $Handler::ACTIVATION_MODE_AUTO:
                $User->activate(false, $SystemUser);
                break;
        }

        return $returnStatus;
    }

    /**
     * Return the success message
     * @return string
     */
    public function getSuccessMessage()
    {
        $registrarSettings = $this->getSettings();
        $settings          = FrontendUsers\Handler::getInstance()->getRegistrationSettings();

        switch ($registrarSettings['activationMode']) {
            case FrontendUsers\Handler::ACTIVATION_MODE_MANUAL:
                $msg = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'message.registrars.email.registration_success_manual'
                );
                break;

            case FrontendUsers\Handler::ACTIVATION_MODE_AUTO:
                $msg = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'message.registrars.email.registration_success_auto'
                );
                break;

            case FrontendUsers\Handler::ACTIVATION_MODE_MAIL:
                $msg = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'message.registrars.email.registration_success_mail'
                );
                break;

            default:
                return parent::getPendingMessage();
        }

        if ($settings['passwordInput'] === FrontendUsers\Handler::PASSWORD_INPUT_SENDMAIL) {
            $msg .= "<p>" . QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'registrars.email.password_auto_generate'
            ) . "</p>";
        }

        return $msg;
    }

    /**
     * Return pending message
     * @return string
     */
    public function getPendingMessage()
    {
        $msg      = parent::getPendingMessage();
        $settings = FrontendUsers\Handler::getInstance()->getRegistrationSettings();

        if ($settings['passwordInput'] === FrontendUsers\Handler::PASSWORD_INPUT_SENDMAIL) {
            $msg .= "<p>" . QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'registrars.email.password_auto_generate'
            ) . "</p>";
        }

        return $msg;
    }

    /**
     * @throws FrontendUsers\Exception
     */
    public function validate()
    {
        $username = $this->getUsername();
        $Handler  = FrontendUsers\Handler::getInstance();
        $settings = $Handler->getRegistrationSettings();
        $lg       = 'quiqqer/frontend-users';
        $lgPrefix = 'exception.registrars.email.';

        if (empty($username)) {
            throw new FrontendUsers\Exception(array(
                'quiqqer/frontend-users',
                $lgPrefix . 'empty_username'
            ));
        }

        try {
            QUI::getUsers()->getUserByName($username);

            // Username already exists
            throw new FrontendUsers\Exception(array(
                'quiqqer/frontend-users',
                $lgPrefix . 'username_already_exists'
            ));
        } catch (\Exception $Exception) {
            // Username does not exist
        }

        $email        = $this->getAttribute('email');
        $emailConfirm = $this->getAttribute('emailConfirm');

        if ($email != $emailConfirm) {
            if (empty($username)) {
                throw new FrontendUsers\Exception(array(
                    'quiqqer/frontend-users',
                    $lgPrefix . 'different_emails'
                ));
            }
        }

        $password        = $this->getAttribute('password');
        $passwordConfirm = $this->getAttribute('passwordConfirm');

        if ($password != $passwordConfirm) {
            if (empty($username)) {
                throw new FrontendUsers\Exception(array(
                    'quiqqer/frontend-users',
                    $lgPrefix . 'different_passwords'
                ));
            }
        }

        // Address validation
        if ((int)$settings['addressInput']) {
            foreach ($Handler->getAddressFieldSettings() as $field => $settings) {
                $val = $this->getAttribute($field);

                if ($settings['required'] && empty($val)) {
                    throw new FrontendUsers\Exception(array(
                        'quiqqer/frontend-users',
                        $lgPrefix . 'missing_address_fields'
                    ));
                }
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
        $L                = QUI::getLocale();
        $lg               = 'quiqqer/frontend-users';
        $lgPrefix         = 'exception.registrars.email.';
        $invalidFields    = array();
        $RegistrarHandler = FrontendUsers\Handler::getInstance();
        $settings         = $RegistrarHandler->getRegistrationSettings();
        $usernameInput    = $settings['usernameInput'];
        $Users            = QUI::getUsers();
        $usernameExists   = $Users->usernameExists($username);

        // Username check
        if ($usernameInput !== 'none') {
            // Check if username input is enabled
            if (empty($username)
                && $usernameInput === 'required') {
                $invalidFields['username'] = new InvalidFormField(
                    'username',
                    $L->get($lg, $lgPrefix . 'empty_username')
                );
            }

            if ($usernameExists) {
                $invalidFields['username'] = new InvalidFormField(
                    'username',
                    $L->get($lg, $lgPrefix . 'username_already_exists')
                );
            }
        } else {
            // Check if username input is not enabled
            if ($usernameExists) {
                $invalidFields['email'] = new InvalidFormField(
                    'email',
                    $L->get($lg, $lgPrefix . 'email_already_exists')
                );
            }
        }

        // Email check
        $email        = $this->getAttribute('email');
        $emailConfirm = $this->getAttribute('emailConfirm');

        if (empty($email)) {
            $invalidFields['email'] = new InvalidFormField(
                'email',
                $L->get($lg, $lgPrefix . 'empty_email')
            );
        }

        if ($Users->emailExists($email)) {
            $invalidFields['email'] = new InvalidFormField(
                'email',
                $L->get($lg, $lgPrefix . 'email_already_exists')
            );
        }

        if ($email != $emailConfirm) {
            $invalidFields['email'] = new InvalidFormField(
                'email',
                $L->get($lg, $lgPrefix . 'different_emails')
            );
        }

        // Password check
        if ($settings['passwordInput'] !== $RegistrarHandler::PASSWORD_INPUT_SENDMAIL) {
            $password        = $this->getAttribute('password');
            $passwordConfirm = $this->getAttribute('passwordConfirm');

            if ($password != $passwordConfirm) {
                $invalidFields['password'] = new InvalidFormField(
                    'password',
                    $L->get($lg, $lgPrefix . 'different_passwords')
                );
            }
        }

        // Address validation
        if ((int)$settings['addressInput']) {
            foreach ($RegistrarHandler->getAddressFieldSettings() as $field => $settings) {
                $val = $this->getAttribute($field);

                if ($settings['required'] && empty($val)) {
                    $invalidFields[$field] = new InvalidFormField(
                        $field,
                        $L->get($lg, $lgPrefix . 'missing_field')
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

        if (isset($data['username'])) {
            return $data['username'];
        }

        if (isset($data['email'])) {
            return $data['email'];
        }

        return '';
    }

    /**
     * @return Control
     */
    public function getControl()
    {
        $invalidFields = array();

        if (!empty($_POST['registration'])) {
            $invalidFields = $this->getInvalidFields();
        }

        return new Control(array(
            'invalidFields' => $invalidFields
        ));
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
}
