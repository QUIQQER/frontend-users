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

        // set e-mail address
        $User->setAttribute('email', $this->getAttribute('email'));

        // set password
        if ($registrationSettings['passwordInput'] === 'sendmail') {
            $randomPass = QUI\Security\Password::generateRandom();
            $User->setPassword($randomPass, $SystemUser);
            // @todo send $randomPass via e-mail

            \QUI\System\Log::writeRecursive($randomPass);
        } else {
            $User->setPassword($this->getAttribute('password'), $SystemUser);
        }

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
        $settings = $this->getSettings();

        switch ($settings['activationMode']) {
            case FrontendUsers\Handler::ACTIVATION_MODE_MANUAL:
                return QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'message.registrars.email.registration_success_manual'
                );
                break;

            case FrontendUsers\Handler::ACTIVATION_MODE_AUTO:
                return QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'message.registrars.email.registration_success_auto'
                );
                break;

            case FrontendUsers\Handler::ACTIVATION_MODE_MAIL:
                return QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'message.registrars.email.registration_success_mail'
                );
                break;

            default:
                return parent::getPendingMessage();
        }
    }

    /**
     * @throws FrontendUsers\Exception
     */
    public function validate()
    {
        $username = $this->getUsername();

        if (empty($username)) {
            throw new FrontendUsers\Exception(array(
                'quiqqer/frontend-users',
                'exception.registrars.email.empty_username'
            ));
        }

        try {
            QUI::getUsers()->getUserByName($username);

            // Username already exists
            throw new FrontendUsers\Exception(array(
                'quiqqer/frontend-users',
                'exception.registrars.email.username_already_exists'
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
                    'exception.registrars.email.different_emails'
                ));
            }
        }

        $password        = $this->getAttribute('password');
        $passwordConfirm = $this->getAttribute('passwordConfirm');

        if ($password != $passwordConfirm) {
            if (empty($username)) {
                throw new FrontendUsers\Exception(array(
                    'quiqqer/frontend-users',
                    'exception.registrars.email.different_passwords'
                ));
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
                    $L->get($lg, 'exception.registrars.email.empty_username')
                );
            }

            if ($usernameExists) {
                $invalidFields['username'] = new InvalidFormField(
                    'username',
                    $L->get($lg, 'exception.registrars.email.username_already_exists')
                );
            }
        } else {
            // Check if username input is not enabled
            if ($usernameExists) {
                $invalidFields['email'] = new InvalidFormField(
                    'email',
                    $L->get($lg, 'exception.registrars.email.email_already_exists')
                );
            }
        }

        // Email check
        $email        = $this->getAttribute('email');
        $emailConfirm = $this->getAttribute('emailConfirm');

        if (empty($email)) {
            $invalidFields['email'] = new InvalidFormField(
                'email',
                $L->get($lg, 'exception.registrars.email.empty_email')
            );
        }

        if ($Users->emailExists($email)) {
            $invalidFields['email'] = new InvalidFormField(
                'email',
                $L->get($lg, 'exception.registrars.email.email_already_exists')
            );
        }

        if ($email != $emailConfirm) {
            $invalidFields['email'] = new InvalidFormField(
                'email',
                $L->get($lg, 'exception.registrars.email.different_emails')
            );
        }

        // Password check
        if ($settings['passwordInput'] !== 'sendmail') {
            $password        = $this->getAttribute('password');
            $passwordConfirm = $this->getAttribute('passwordConfirm');

            if ($password != $passwordConfirm) {
                $invalidFields['password'] = new InvalidFormField(
                    'password',
                    $L->get($lg, 'exception.registrars.email.different_passwords')
                );
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
