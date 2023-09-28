<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\ChangePassword
 */

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;

/**
 * Class ChangePassword
 *
 * Change user password
 */
class ChangePassword extends AbstractProfileControl
{
    /**
     * ChangePassword constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-changepassword');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');

        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/profile/ChangePassword');
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign([
            'User' => QUI::getUserBySession()
        ]);

        $this->addCSSFile(dirname(__FILE__) . '/ChangePassword.css');

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
        $User = $this->getAttribute('User');

        $passwordOld = $Request->get('passwordOld');
        $passwordNew = $Request->get('passwordNew');

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
