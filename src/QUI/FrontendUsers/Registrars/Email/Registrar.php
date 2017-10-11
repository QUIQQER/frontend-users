<?php

/**
 * This file contains QUI\FrontendUsers\Registrars\Email\Registrar
 */

namespace QUI\FrontendUsers\Registrars\Email;

use QUI;
use QUI\FrontendUsers;

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
        $Handler    = FrontendUsers\Handler::getInstance();
        $settings   = $this->getSettings();
        $SystemUser = QUI::getUsers()->getSystemUser();

        $User->setAttribute('email', $this->getAttribute('email'));
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
        return new Control();
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
