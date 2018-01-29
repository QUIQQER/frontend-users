<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\ChangePassword
 */

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\Control;

/**
 * Class ChangePassword
 *
 * Change user password
 */
class ChangePassword extends Control
{
    /**
     * ChangePassword constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-changepassword');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');

        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/profile/ChangePassword');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine   = QUI::getTemplateManager()->getEngine();

        $Engine->assign(array(
            'User' => QUI::getUserBySession()
        ));

        return $Engine->fetch(dirname(__FILE__) . '/ChangePassword.html');
    }

    /**
     * event: on save
     *
     * @throws QUI\FrontendUsers\Exception
     */
    public function onSave()
    {
        $Request = QUI::getRequest()->request;

        if (!$Request->get('profile-save')) {
            return;
        }

        $passwordOld = $Request->get('passwordOld');
        $passwordNew = $Request->get('passwordNew');

        $User = $this->getAttribute('User');

        if (!$User) {
            $User = QUI::getUserBySession();
        }

        try {
            /** @var QUI\Users\User $User */
            $User->changePassword($passwordNew, $passwordOld);
        } catch (\Exception $Exception) {
            if ($Exception->getCode() === 401) {
                $msg = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'exception.controls.profile.changepassword.wrong_password'
                );
            } else {
                $msg = $Exception->getMessage();
            }

            throw new QUI\FrontendUsers\Exception($msg);
        }
    }
}
