<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\UserData
 */

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\Utils\Security\Orthos;
use QUI\FrontendUsers\Handler as FrontendUsersHandler;

/**
 * Class UserData
 *
 * Change basic User data
 */
class UserData extends AbstractProfileControl
{
    /**
     * UserData constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-userdata');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');
        $this->addCSSFile(dirname(__FILE__).'/UserData.css');

        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData');
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $action               = false;
        $emailChangeRequested = true;

        $User             = QUI::getUserBySession();
        $Engine           = QUI::getTemplateManager()->getEngine();
        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();

        if (!empty($_REQUEST['action'])) {
            $action = $_REQUEST['action'];
        }

        try {
            QUI\Verification\Verifier::getVerificationByIdentifier(
                $User->getId(),
                QUI\FrontendUsers\EmailConfirmVerification::getType(),
                true
            );
        } catch (\Exception $Exception) {
            $emailChangeRequested = false;
        }

        /* @var $User QUI\Users\User */
        try {
            $Address = $User->getStandardAddress();
        } catch (QUI\Users\Exception $Exception) {
            $Address = $User->addAddress([]);
        }

        $Engine->assign(array(
            'User'              => $User,
            'Address'           => $Address,
            'action'            => $action,
            'changeMailRequest' => $emailChangeRequested,
            'username'          => $RegistrarHandler->isUsernameInputAllowed()
        ));

        return $Engine->fetch(dirname(__FILE__).'/UserData.html');
    }

    /**
     * event: on save
     *
     * @throws QUI\FrontendUsers\Exception
     * @throws QUI\Exception
     */
    public function onSave()
    {
        $Request  = QUI::getRequest()->request;
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
        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();

        $allowedFields = array(
            'firstname',
            'lastname',
            'birthday'
        );

        // allow edit of username if username can be set on registration
        if ($RegistrarHandler->isUsernameInputAllowed()) {
            $allowedFields[] = 'username';
        }

        // special case: birthday
        $bday = '';

        if ($Request->get('birth_year')
            && $Request->get('birth_month')
            && $Request->get('birth_day')) {
            $bday .= $Request->get('birth_year');
            $bday .= '-'.$Request->get('birth_month');
            $bday .= '-'.$Request->get('birth_day');
            $Request->set('birthday', $bday);
        }

        foreach ($allowedFields as $field) {
            if ($Request->get($field)) {
                $User->setAttribute($field, $Request->get($field));
            }
        }

        $User->save();
    }
}
