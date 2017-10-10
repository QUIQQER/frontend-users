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
        $RegistratorHandler   = QUI\FrontendUsers\Handler::getInstance();
        $Registrators         = $RegistratorHandler->getRegistrators();
        $registrationSettings = $RegistratorHandler->getRegistrationSettings();

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
            'Registrators'      => $Registrators,
            'Registrator'       => $this->isCurrentlyExecuted(),
            'data'              => $this->getAttribute('data'),
            'showUsernameInput' => boolval($registrationSettings['usernameInput']),
            'showAddressInput'  => boolval($registrationSettings['addressInput'])
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Registration.html');
    }

    /**
     * Is registration started?
     *
     * @return bool|QUI\FrontendUsers\RegistratorInterface
     */
    protected function isCurrentlyExecuted()
    {
        if (!isset($_POST['registration'])) {
            return false;
        }

        if (!isset($_POST['registrator'])) {
            return false;
        }

        $FrontendUsers = QUI\FrontendUsers\Handler::getInstance();
        $Registrators  = $FrontendUsers->getRegistrators();

        foreach ($Registrators as $Registrator) {
            if (get_class($Registrator) === $_POST['registrator']) {
                return $Registrator;
            }
        }

        return false;
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

        $Registrator = $this->isCurrentlyExecuted();

        if ($Registrator === false) {
            throw new QUI\FrontendUsers\Exception(array(
                'quiqqer/frontend-users',
                'exception.registrator.not.found'
            ));
        }

        $RegistratorHandler   = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistratorHandler->getRegistrationSettings();

        $Registrator->setAttributes($_POST);
        $Registrator->validate();

        $NewUser = $Registrator->createUser();

        $defaultGroups = explode(",", $registrationSettings['defaultGroups']);

        foreach ($defaultGroups as $groupId) {
            $NewUser->addToGroup($groupId);
        }

        $NewUser->save(QUI::getUsers()->getSystemUser());

        return $Registrator->onRegistered($NewUser);
    }
}