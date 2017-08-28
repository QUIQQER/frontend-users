<?php

/**
 * This file contains QUI\FrontendUsers\Registrars\Email\Registrator
 */

namespace QUI\FrontendUsers\Registrars\Email;

use QUI;
use QUI\FrontendUsers;

/**
 * Class EMail
 *
 * @package QUI\FrontendUsers\Registrars
 */
class Registrator extends FrontendUsers\AbstractRegistrator
{
    /**
     * @param QUI\Interfaces\Users\User $User
     * @return int
     */
    public function onRegistered(QUI\Interfaces\Users\User $User)
    {
        $User->setAttribute('email', $this->getAttribute('email'));

        // @todo send activation mail


        return FrontendUsers\Handler::REGISTRATION_STATUS_PENDING;
    }

    /**
     * @return array|string
     */
    public function getPendingMessage()
    {
        return QUI::getLocale()->get('quiqqer/frontend-users', 'message.registration.mail.was.send');
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
                'exception.empty.username'
            ));
        }

        $email        = $this->getAttribute('email');
        $emailConfirm = $this->getAttribute('emailConfirm');

        if ($email != $emailConfirm) {
            if (empty($username)) {
                throw new FrontendUsers\Exception(array(
                    'quiqqer/frontend-users',
                    'exception.different.emails'
                ));
            }
        }

        $password        = $this->getAttribute('password');
        $passwordConfirm = $this->getAttribute('passwordConfirm');

        if ($password != $passwordConfirm) {
            if (empty($username)) {
                throw new FrontendUsers\Exception(array(
                    'quiqqer/frontend-users',
                    'exception.different.passwords'
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
}
