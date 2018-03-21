<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Registration
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\FrontendUsers\Controls\Auth\FrontendLogin;
use QUI\Projects\Site\Utils as QUISiteUtils;

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
     * Registration ID (for this runtime only)
     *
     * @var string
     */
    protected $id;

    /**
     * The User that is registered in the current runtime
     *
     * @var QUI\Users\User
     */
    protected $RegisteredUser = null;

    /**
     * Registration constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttributes([
            'data-qui'  => 'package/quiqqer/frontend-users/bin/frontend/controls/Registration',
            'status'    => false,
            'Registrar' => false    // currently executed Registrar
        ]);

        $this->setAttributes($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Registration.css');
        $this->id = QUI\FrontendUsers\Handler::getInstance()->createRegistrationId();
    }

    /**
     * Return the html body
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine               = QUI::getTemplateManager()->getEngine();
        $RegistrarHandler     = QUI\FrontendUsers\Handler::getInstance();
        $Registrars           = $RegistrarHandler->getRegistrars();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();
        $CurrentRegistrar     = $this->isCurrentlyExecuted();
        $registrationStatus   = false;
        $projectLang          = QUI::getRewrite()->getProject()->getLang();

        // execute registration process
        if (isset($_POST['registration'])
            && $_POST['registration_id'] === $this->id) {
            try {
                $registrationStatus = $this->register();

                $Engine->assign([
                    'registrationStatus' => $registrationStatus
                ]);
            } catch (QUI\Exception $Exception) {
                $Engine->assign('error', $Exception->getMessage());
            }
        }

        // check for errors
        $status = $this->getAttribute('status');

        if ($status === $RegistrarHandler::REGISTRATION_STATUS_ERROR && $CurrentRegistrar) {
            $Engine->assign('error', $CurrentRegistrar->getErrorMessage());
        } elseif ($status === 'error') {
            $Engine->assign('error', QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'control.registration.general_error'
            ));
        }

        // determine success
        $success = $CurrentRegistrar
                   && ($status === 'success'
                       || $registrationStatus === $RegistrarHandler::REGISTRATION_STATUS_SUCCESS);

        // redirect directives
        $redirectUrl     = false;
        $instantRedirect = false;

        if ($success) {
            if ($this->RegisteredUser
                && $this->RegisteredUser->isActive()
                && $registrationSettings['autoLoginOnActivation']
            ) {
                // instantly redirect (only used on auto-login)
                $loginSettings   = $RegistrarHandler->getLoginSettings();
                $redirectOnLogin = $loginSettings['redirectOnLogin'];
                $Project         = $this->getProject();
                $projectLang     = $Project->getLang();

                try {
                    if (!empty($redirectOnLogin[$projectLang])) {
                        $RedirectSite = QUISiteUtils::getSiteByLink($redirectOnLogin[$projectLang]);
                    } else {
                        $RedirectSite = $Project->get(1);
                    }

                    $redirectUrl     = $RedirectSite->getUrlRewrittenWithHost();
                    $instantRedirect = true;
                } catch (\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            } elseif (!empty($registrationSettings['autoRedirectOnSuccess'][$projectLang])) {
                // show success message and redirect after 10 seconds
                try {
                    $RedirectSite = QUI\Projects\Site\Utils::getSiteByLink(
                        $registrationSettings['autoRedirectOnSuccess'][$projectLang]
                    );

                    $redirectUrl = $RedirectSite->getUrlRewrittenWithHost();
                } catch (\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        // determine if Login control is shown
        $Login = false;

        if (!QUI::getUserBySession()->getId()) {
            $Login = new FrontendLogin([
                'showRegistration' => false
            ]);
        }

        // Terms Of Use
        $termsOfUseRequired = false;
        $termsOfUseLabel    = '';

        if ($registrationSettings['termsOfUseRequired']
            && !empty($registrationSettings['termsOfUseSite'][$projectLang])) {
            try {
                $TermsOfUseSite  = QUI\Projects\Site\Utils::getSiteByLink($registrationSettings['termsOfUseSite'][$projectLang]);
                $termsOfUseLabel = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'control.registration.terms_of_use.label',
                    [
                        'termsOfUseUrl'       => $TermsOfUseSite->getUrlRewrittenWithHost(),
                        'termsOfUseSiteTitle' => $TermsOfUseSite->getAttribute('title')
                    ]
                );

                $Engine->assign([
                    'termsOfUseSiteId' => $TermsOfUseSite->getId()
                ]);

                $termsOfUseRequired = true;
            } catch (\Exception $Exception) {
                // nothing
            }
        }

        // Sort registrars by display position
        $Registrars->sort(function ($RegistrarA, $RegistrarB) use ($RegistrarHandler) {
            $settingsA        = $RegistrarHandler->getRegistrarSettings(get_class($RegistrarA));
            $settingsB        = $RegistrarHandler->getRegistrarSettings(get_class($RegistrarB));
            $displayPositionA = (int)$settingsA['displayPosition'];
            $displayPositionB = (int)$settingsB['displayPosition'];

            return $displayPositionA - $displayPositionB;
        });

        $Engine->assign([
            'Registrars'          => $Registrars,
            'Registrar'           => $CurrentRegistrar,
            'success'             => $success,
            'redirectUrl'         => $redirectUrl,
            'instantRedirect'     => $instantRedirect,
            'Login'               => $Login,
            'termsOfUseLabel'     => $termsOfUseLabel,
            'termsOfUseRequired'  => $termsOfUseRequired,
            'termsOfUseAcctepted' => !empty($_POST['termsOfUseAccepted']),
            'registrationId'      => $this->id,
            'showRegistrarTitle'  => $this->getAttribute('showRegistrarTitle')
        ]);

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

        $this->setAttribute('Registrar', $Registrar);

        return $Registrar;
    }

    /**
     * Execute the Registration
     *
     * @throws QUI\FrontendUsers\Exception
     * @throws QUI\Exception
     */
    public function register()
    {
        if (!isset($_POST['registration'])) {
            return QUI\FrontendUsers\Handler::REGISTRATION_STATUS_ERROR;
        }

        /** @var QUI\FrontendUsers\RegistrarInterface $Registrar */
        $Registrar = $this->isCurrentlyExecuted();

        if ($Registrar === false) {
            throw new QUI\FrontendUsers\Exception([
                'quiqqer/frontend-users',
                'exception.registration.registrar_not_found'
            ]);
        }

        $RegistrarHandler     = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();
        $Project              = QUI::getRewrite()->getProject();
        $Registrar->setProject($Project);
        $Registrar->setAttributes($_POST);

        // check Terms Of Use
        if (!empty($registrationSettings['termsOfUseRequired'])
            && empty($_POST['termsOfUseAccepted'])) {
            throw new QUI\FrontendUsers\Exception([
                'quiqqer/frontend-users',
                'exception.registration.terms_of_use_not_accepted'
            ]);
        }

        $Registrar->validate();

        // create user
        $NewUser = $Registrar->createUser();

        // add user to default groups
        $defaultGroups = explode(",", $registrationSettings['defaultGroups']);

        foreach ($defaultGroups as $groupId) {
            $NewUser->addToGroup($groupId);
        }

        // set registration/registrar data to user
        $NewUser->setAttributes([
            $RegistrarHandler::USER_ATTR_REGISTRATION_PROJECT      => $Project->getName(),
            $RegistrarHandler::USER_ATTR_REGISTRATION_PROJECT_LANG => $Project->getLang(),
            $RegistrarHandler::USER_ATTR_REGISTRAR                 => $Registrar->getType(),
            $RegistrarHandler::USER_ATTR_USER_ACTIVATION_REQUIRED  => true
        ]);

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
        $result = QUI::getDataBase()->fetch([
            'select' => 'password',
            'from'   => QUI::getDBTableName('users'),
            'where'  => [
                'id' => $NewUser->getId()
            ],
            'limit'  => 1
        ]);

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
                    throw new QUI\FrontendUsers\Exception([
                        'quiqqer/frontend-users',
                        'exception.registration.send_mail_error'
                    ]);
                }

                $registrationStatus = $RegistrarHandler::REGISTRATION_STATUS_PENDING;
                break;

            case $RegistrarHandler::ACTIVATION_MODE_AUTO:
                $NewUser->activate(false, $SystemUser);
                break;
        }

        $this->RegisteredUser = $NewUser;

        return $registrationStatus;
    }
}
