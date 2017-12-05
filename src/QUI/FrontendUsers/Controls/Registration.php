<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Registration
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\FrontendUsers\Controls\Auth\FrontendLogin;

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
            'data-qui'           => 'package/quiqqer/frontend-users/bin/frontend/controls/Registration',
            'status'             => false,
            'Registrar'          => false,    // currently executed Registrar
            'showRegistrarTitle' => true
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
        $CurrentRegistrar     = $this->isCurrentlyExecuted();
        $registrationStatus   = false;

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

        $status = $this->getAttribute('status');

        if ($status === $RegistrarHandler::REGISTRATION_STATUS_ERROR && $CurrentRegistrar) {
            $Engine->assign('error', $CurrentRegistrar->getErrorMessage());
        }

        // auto-redirect on success
        $autoRedirect = false;

        if (!empty($registrationSettings['autoRedirectOnSuccess'])) {
            $autoRedirect = $registrationSettings['autoRedirectOnSuccess'];

            try {
                $RedirectSite = QUI\Projects\Site\Utils::getSiteByLink($autoRedirect);
                $autoRedirect = $RedirectSite->getUrlRewrittenWithHost();
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                $autoRedirect = false;
            }
        }

        $status  = $this->getAttribute('status');
        $success = $CurrentRegistrar
                   && ($status === 'success'
                       || $registrationStatus === $RegistrarHandler::REGISTRATION_STATUS_SUCCESS);

        $Login = false;

        if (QUI::getUserBySession()->getId()) {
            $Login = new FrontendLogin(array(
                'showRegistration' => false
            ));
        }

        $Engine->assign(array(
            'Registrars'         => $Registrars,
            'Registrar'          => $CurrentRegistrar,
            'success'            => $success,
            'autoRedirect'       => $autoRedirect,
            'Login'              => $Login,
            'showRegistrarTitle' => $this->getAttribute('showRegistrarTitle')
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
        $Registrar     = $this->getAttribute('Registrar');

        if ($Registrar
            && $Registrar instanceof QUI\FrontendUsers\RegistrarInterface
            && $Registrar->isActive()) {
            return $Registrar;
        }

        if (!isset($_POST['registration'])) {
            return false;
        }

        if (!isset($_POST['registrar'])) {
            return false;
        }

        $Registrar = $FrontendUsers->getRegistrarByHash($_POST['registrar']);

        if (!$Registrar) {
            return false;
        }

        if (!$Registrar->isActive()) {
            return false;
        }

        return $Registrar;
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

        /** @var QUI\FrontendUsers\RegistrarInterface $Registrar */
        $Registrar = $this->isCurrentlyExecuted();

        if ($Registrar === false) {
            throw new QUI\FrontendUsers\Exception(array(
                'quiqqer/frontend-users',
                'exception.registration.registrar_not_found'
            ));
        }

        $RegistrarHandler     = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();
        $Project              = QUI::getRewrite()->getProject();

        $Registrar->setProject($Project);
        $Registrar->setAttributes($_POST);
        $Registrar->validate();

        // create user
        $NewUser = $Registrar->createUser();

        // add user to default groups
        $defaultGroups = explode(",", $registrationSettings['defaultGroups']);

        foreach ($defaultGroups as $groupId) {
            $NewUser->addToGroup($groupId);
        }

        // set registration/registrar data to user
        $NewUser->setAttributes(array(
            $RegistrarHandler::USER_ATTR_REGISTRATION_PROJECT      => $Project->getName(),
            $RegistrarHandler::USER_ATTR_REGISTRATION_PROJECT_LANG => $Project->getLang(),
            $RegistrarHandler::USER_ATTR_REGISTRAR                 => $Registrar->getType(),
            $RegistrarHandler::USER_ATTR_USER_ACTIVATION_REQUIRED  => true
        ));

        // handle onRegistered from Registrar
        $Registrar->onRegistered($NewUser);
        $settings          = $RegistrarHandler->getRegistrationSettings();
        $registrarSettings = $RegistrarHandler->getRegistrarSettings($Registrar->getType());

        // determine if the user has to set a new password on first login
        if (boolval($settings['forcePasswordReset'])) {
            $NewUser->setAttribute('quiqqer.set.new.password', true);
        }

        // send registration notice to admins
        $RegistrarHandler->sendRegistrationNotice($NewUser, $Registrar->getProject());

        $NewUser->save(QUI::getUsers()->getSystemUser());

        // check if the user has a password
        $result = QUI::getDataBase()->fetch(array(
            'select' => 'password',
            'from'   => QUI::getDBTableName('users'),
            'where'  => array(
                'id' => $NewUser->getId()
            ),
            'limit'  => 1
        ));

        $SystemUser = QUI::getUsers()->getSystemUser();

        // set random password if the Registrar did not set a password
        if (empty($result[0]['password'])) {
            $NewUser->setPassword(QUI\Security\Password::generateRandom(), $SystemUser);
        }

        // determine registration status
        $registrationStatus = $RegistrarHandler::REGISTRATION_STATUS_SUCCESS;

        switch ($registrarSettings['activationMode']) {
            case $RegistrarHandler::ACTIVATION_MODE_MAIL:
                $sendMailSuccess = $RegistrarHandler->sendActivationMail($NewUser, $Registrar);

                if (!$sendMailSuccess) {
                    throw new QUI\FrontendUsers\Exception(array(
                        'quiqqer/frontend-users',
                        'exception.registration.send_mail_error'
                    ));
                }

                $registrationStatus = $RegistrarHandler::REGISTRATION_STATUS_PENDING;
                break;

            case $RegistrarHandler::ACTIVATION_MODE_AUTO:
                $NewUser->activate(false, $SystemUser);
                break;
        }

        return $registrationStatus;
    }
}
