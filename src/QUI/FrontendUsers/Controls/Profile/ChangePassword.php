<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\ChangePassword
 */

namespace QUI\FrontendUsers\Controls\Profile;

use Exception;
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

        if (!defined('QUIQQER_CONTROL_TEMPLATE_USE_BASIC') || QUIQQER_CONTROL_TEMPLATE_USE_BASIC !== true) {
            $this->addCSSFile(dirname(__FILE__) . '/ChangePassword.css');
        }

        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/profile/ChangePassword');
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign([
            'User' => QUI::getUserBySession()
        ]);

        return $Engine->fetch($this->getTemplateFile());
    }

    /**
     * event: on save
     *
     * @throws QUI\FrontendUsers\Exception
     */
    public function onSave(): void
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
        } catch (Exception $Exception) {
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
