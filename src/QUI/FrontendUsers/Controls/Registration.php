<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Registration
 */

namespace QUI\FrontendUsers\Controls;

use QUI;

/**
 * Class Registration
 * - Registration Display
 * - Display all Registration Control
 * - GUI for the Registration
 *
 * @package QUI\FrontendUsers\Controls
 */
class Registration extends QUI\Control
{
    /**
     * Registration constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->setAttributes(array(
            'data-qui' => 'package/quiqqer/frontend-users/bin/frontend/controls/Registration',
            'data'     => array()
        ));

        $this->setAttributes($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Registration.css');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getBody()
    {
        $Engine               = QUI::getTemplateManager()->getEngine();
        $RegistrarHandler     = QUI\FrontendUsers\Handler::getInstance();
        $Registrars           = $RegistrarHandler->getRegistrars();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();

        if (isset($_POST['registration'])) {
            try {
                $registrationStatus = $this->register();

                $Engine->assign(array(
                    'registrationStatus' => $registrationStatus
                ));
            } catch (QUI\Exception $Exception) {
                $Engine->assign('error', $Exception->getMessage());
            }
        }

        $Engine->assign(array(
            'Registrars'        => $Registrars,
            'Registrar'         => $this->isCurrentlyExecuted(),
            'data'              => $this->getAttribute('data'),
            'showUsernameInput' => boolval($registrationSettings['usernameInput']),
            'showAddressInput'  => boolval($registrationSettings['addressInput'])
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Registration.html');
    }

    /**
     * Is registration started?
     *
     * @return bool|QUI\FrontendUsers\RegistrarInterface
     */
    protected function isCurrentlyExecuted()
    {
        $FrontendUsers = QUI\FrontendUsers\Handler::getInstance();
        $registrar     = QUI::getSession()->get($FrontendUsers::SESSION_REGISTRAR);

        if ($registrar) {
            return $FrontendUsers->getRegistrar($registrar);
        }

        if (!isset($_POST['registration'])) {
            return false;
        }

        if (!isset($_POST['registrar'])) {
            return false;
        }

        return $FrontendUsers->getRegistrar($_POST['registrar']);
    }

    /**
     * Execute the Registration
     *
     * @throws QUI\FrontendUsers\Exception
     */
    public function register()
    {
        if (!isset($_POST['registration'])) {
            return QUI\FrontendUsers\Handler::REGISTRATION_STATUS_ERROR;
        }

        /** @var QUI\FrontendUsers\AbstractRegistrar $Registrar */
        $Registrar = $this->isCurrentlyExecuted();

        if ($Registrar === false) {
            throw new QUI\FrontendUsers\Exception(array(
                'quiqqer/frontend-users',
                'exception.registrar.not.found'
            ));
        }

        $RegistrarHandler     = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();

        $Registrar->setProject(QUI::getRewrite()->getProject());
        $Registrar->setAttributes($_POST);
        $Registrar->validate();

        // create user
        $NewUser = $Registrar->createUser();

        // add user to default groups
        $defaultGroups = explode(",", $registrationSettings['defaultGroups']);

        foreach ($defaultGroups as $groupId) {
            $NewUser->addToGroup($groupId);
        }

        $NewUser->save(QUI::getUsers()->getSystemUser());

        // handle onRegistered from Registrar
        $registratinoStatus = $Registrar->onRegistered($NewUser);

        // send registration notice to admins
        $RegistrarHandler->sendRegistrationNotice($NewUser, $Registrar->getProject());

        return $registratinoStatus;
    }
}