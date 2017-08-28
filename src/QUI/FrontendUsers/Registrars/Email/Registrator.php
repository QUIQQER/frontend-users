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
        return FrontendUsers\Handler::REGISTRATION_STATUS_PENDING;
    }

    /**
     * @throws FrontendUsers\Exception
     */
    public function validate()
    {
        $username = $this->getUsername();

        if (empty($username)) {
            throw new FrontendUsers\Exception(array(
                'quiqqer/frontend-user',
                'exception.empty.username'
            ));
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
