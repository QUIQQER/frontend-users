<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\UserData
 */

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\Control;
use QUI\Utils\Security\Orthos;
use QUI\FrontendUsers\Handler as FrontendUsersHandler;

/**
 * Class UserData
 *
 * Change basic User data
 */
class UserData extends Control
{
    /**
     * ControlWrapper constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-userdata');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');
        $this->addCSSFile(dirname(__FILE__) . '/UserData.css');

        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $action = false;

        if (!empty($_REQUEST['action'])) {
            $action = $_REQUEST['action'];
        }

        $Engine->assign(array(
            'User'   => QUI::getUserBySession(),
            'action' => $action
        ));

        return $Engine->fetch(dirname(__FILE__) . '/UserData.html');
    }

    /**
     * event: on save
     */
    public function onSave()
    {
        $Request = QUI::getRequest()->request;

        if (!$Request->get('profile-save')) {
            return;
        }

        $newEmail = $Request->get('emailNew');
        $User     = QUI::getUserBySession();

        if (!empty($newEmail)) {
            if (!Orthos::checkMailSyntax($newEmail)) {
                throw new QUI\FrontendUsers\Exception(array(
                    'quiqqer/frontend-users',
                    'exception.controls.profile.userdata.invalid_new_email_address'
                ));
            }

            if (QUI::getUsers()->emailExists($newEmail)) {
                throw new QUI\FrontendUsers\Exception(array(
                    'quiqqer/frontend-users',
                    'exception.controls.profile.userdata.new_email_address_already_exists'
                ));
            }

            if ($newEmail === $User->getAttribute('email')) {
                throw new QUI\FrontendUsers\Exception(array(
                    'quiqqer/frontend-users',
                    'exception.controls.profile.userdata.new_email_address_no_change'
                ));
            }

            FrontendUsersHandler::getInstance()->sendChangeEmailAddressMail(
                $User,
                $newEmail,
                QUI::getRewrite()->getProject()
            );
        }

        // user data
        $allowedFields = array(
            'firstname',
            'lastname',
            'birthday'
        );

        foreach ($allowedFields as $field) {

        }
    }
}
