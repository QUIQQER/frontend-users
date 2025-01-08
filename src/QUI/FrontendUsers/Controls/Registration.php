<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Registration
 */

namespace QUI\FrontendUsers\Controls;

use Exception;
use QUI;
use QUI\FrontendUsers\Controls\Auth\FrontendLogin;
use QUI\FrontendUsers\RegistrarCollection;
use QUI\FrontendUsers\RegistrationUtils;
use QUI\Projects\Site\Utils as QUISiteUtils;

use function mb_strlen;
use function str_replace;

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
    protected string $id;

    /**
     * The User that is registered in the current runtime
     *
     * @var ?QUI\Interfaces\Users\User
     */
    protected ?QUI\Interfaces\Users\User $RegisteredUser = null;

    /**
     * Flag that indicates if the registration process is performed via async
     * or true POST request
     *
     * @var bool
     */
    protected mixed $isAsync = false;

    /**
     * Registration constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttributes([
            'async' => false,
            'data-qui' => 'package/quiqqer/frontend-users/bin/frontend/controls/Registration',
            'status' => false,
            'Registrar' => false,
            // currently executed Registrar
            'registrars' => [],
            // if empty load all default Registrars, otherwise load the ones provided here
            'addressValidation' => true
            // validate address fields (if option is selected in settings; false NEVER validates address data)
        ]);

        $this->setAttributes($attributes);

        $this->setJavaScriptControlOption('registrars', json_encode($this->getAttribute('registrars')));
        $this->addCSSFile(dirname(__FILE__) . '/Registration.css');

        $this->id = QUI\FrontendUsers\Handler::getInstance()->createRegistrationId();
        $this->isAsync = $this->getAttribute('async');
    }

    /**
     * Return the html body
     *
     * @return string
     *
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
        $Registrars = $this->getRegistrars();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();
        $CurrentRegistrar = $this->isCurrentlyExecuted();
        $registrationStatus = false;
        $projectLang = QUI::getRewrite()->getProject()->getLang();

        // execute registration process
        if (
            isset($_POST['registration'])
            && ($this->isAsync || $_POST['registration_id'] === $this->id)
        ) {
            try {
                $registrationStatus = $this->register();

                $Engine->assign([
                    'registrationStatus' => $registrationStatus
                ]);
            } catch (QUI\FrontendUsers\Exception\UserAlreadyExistsException $Exception) {
                QUI\System\Log::writeDebugException($Exception);
                $Engine->assign('error', $Exception->getMessage());
            } catch (QUI\FrontendUsers\Exception $Exception) {
                QUI\System\Log::write(
                    $Exception->getMessage(),
                    QUI\System\Log::LEVEL_WARNING,
                    [
                        'process' => 'registration',
                        'registrar' => $CurrentRegistrar ? $CurrentRegistrar->getTitle() : 'unknown'
                    ],
                    'frontend-users'
                );

                QUI\System\Log::writeDebugException($Exception);

                $Engine->assign('error', $Exception->getMessage());
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                $Engine->assign(
                    'error',
                    QUI::getLocale()->get('quiqqer/frontend-user', 'controls.Registation.general_error')
                );
            }
        }

        // check for errors
        $status = $this->getAttribute('status');

        if ($status === $RegistrarHandler::REGISTRATION_STATUS_ERROR && $CurrentRegistrar) {
            $Engine->assign('error', $CurrentRegistrar->getErrorMessage());
        } elseif ($status === 'error') {
            $Engine->assign([
                'error' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'control.registration.general_error'
                ),
                'isGeneralError' => true
            ]);
        }

        // determine success
        $success = $CurrentRegistrar
            && ($status === 'success'
                || $registrationStatus === $RegistrarHandler::REGISTRATION_STATUS_SUCCESS);

        // redirect directives
        $redirectUrl = false;
        $instantRedirect = false;
        $instantReload = false;

        if ($success) {
            $instantReload = !empty($registrationSettings['reloadOnSuccess']);

            if (
                !$instantReload &&
                $this->RegisteredUser &&
                $this->RegisteredUser->isActive() &&
                $registrationSettings['autoLoginOnActivation']
            ) {
                // instantly redirect (only used on auto-login)
                $loginSettings = $RegistrarHandler->getLoginSettings();
                $redirectOnLogin = $loginSettings['redirectOnLogin'];
                $Project = $this->getProject();
                $projectLang = $Project->getLang();

                try {
                    if (!empty($redirectOnLogin[$projectLang])) {
                        $RedirectSite = QUISiteUtils::getSiteByLink($redirectOnLogin[$projectLang]);
                        $redirectUrl = $RedirectSite->getUrlRewrittenWithHost();
                    }

                    $instantRedirect = true;
                } catch (Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }

            if (!$instantReload && !$redirectUrl && !empty($registrationSettings['autoRedirectOnSuccess'][$projectLang])) {
                // show success message and redirect after 10 seconds
                try {
                    $RedirectSite = QUI\Projects\Site\Utils::getSiteByLink(
                        $registrationSettings['autoRedirectOnSuccess'][$projectLang]
                    );

                    $redirectUrl = $RedirectSite->getUrlRewrittenWithHost();
                } catch (Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        // Behaviour if user is already logged in
        $loggedIn = QUI::getUsers()->isAuth(QUI::getUserBySession());

        if (!$success && $loggedIn) {
            switch ($registrationSettings['visitRegistrationSiteBehaviour']) {
                case 'showProfile':
                    $ProfileSite = $RegistrarHandler->getProfileSite(QUI::getRewrite()->getProject());

                    if ($ProfileSite) {
                        header('Location: ' . $ProfileSite->getUrlRewritten());
                        exit;
                    }
                    break;

                case 'showMessage':
                    $Engine->assign(
                        'error',
                        QUI::getLocale()->get(
                            'quiqqer/frontend-users',
                            'message.types.registration.already_registered'
                        )
                    );
                    break;
            }
        }

        // determine if Login control is shown
        $Login = false;

        if (!QUI::getUserBySession()->getUUID()) {
            $Login = new FrontendLogin([
                'showRegistration' => false
            ]);
        }

        // Terms Of Use
        $termsOfUseRequired = false;
        $termsOfUseLabel = '';

        if (
            $registrationSettings['termsOfUseRequired']
            && (!empty($registrationSettings['termsOfUseSite'][$projectLang]) || !empty($registrationSettings['privacyPolicySite'][$projectLang]))
        ) {
            try {
                $TermsOfUseSite = false;
                $PrivacyPolicySite = false;

                if (!empty($registrationSettings['termsOfUseSite'][$projectLang])) {
                    $TermsOfUseSite = QUISiteUtils::getSiteByLink(
                        $registrationSettings['termsOfUseSite'][$projectLang]
                    );
                }

                if (!empty($registrationSettings['privacyPolicySite'][$projectLang])) {
                    $PrivacyPolicySite = QUISiteUtils::getSiteByLink(
                        $registrationSettings['privacyPolicySite'][$projectLang]
                    );
                }

                // determine the label for terms of use / privacy policy checkbox
                if ($TermsOfUseSite && $PrivacyPolicySite) {
                    $termsOfUseLabel = QUI::getLocale()->get(
                        'quiqqer/frontend-users',
                        'control.registration.terms_of_use_and_privacy_policy.label',
                        [
                            'termsOfUseUrl' => $TermsOfUseSite->getUrlRewrittenWithHost(),
                            'termsOfUseSiteTitle' => $TermsOfUseSite->getAttribute('title'),
                            'privacyPolicyUrl' => $PrivacyPolicySite->getUrlRewrittenWithHost(),
                            'privacyPolicySiteTitle' => $PrivacyPolicySite->getAttribute('title')
                        ]
                    );

                    $Engine->assign([
                        'termsOfUseSiteId' => $TermsOfUseSite->getId(),
                        'privacyPolicySiteId' => $PrivacyPolicySite->getId()
                    ]);
                } elseif ($TermsOfUseSite) {
                    $termsOfUseLabel = QUI::getLocale()->get(
                        'quiqqer/frontend-users',
                        'control.registration.terms_of_use.label',
                        [
                            'termsOfUseUrl' => $TermsOfUseSite->getUrlRewrittenWithHost(),
                            'termsOfUseSiteTitle' => $TermsOfUseSite->getAttribute('title')
                        ]
                    );

                    $Engine->assign([
                        'termsOfUseSiteId' => $TermsOfUseSite->getId()
                    ]);
                } elseif ($PrivacyPolicySite) {
                    $termsOfUseLabel = QUI::getLocale()->get(
                        'quiqqer/frontend-users',
                        'control.registration.privacy_policy.label',
                        [
                            'privacyPolicyUrl' => $PrivacyPolicySite->getUrlRewrittenWithHost(),
                            'privacyPolicySiteTitle' => $PrivacyPolicySite->getAttribute('title')
                        ]
                    );

                    $Engine->assign([
                        'privacyPolicySiteId' => $PrivacyPolicySite->getId()
                    ]);
                }

                $termsOfUseRequired = true;
            } catch (Exception) {
                // nothing
            }
        }

        // Sort registrars by display position
        $Registrars->sort(function ($RegistrarA, $RegistrarB) use ($RegistrarHandler) {
            $settingsA = $RegistrarHandler->getRegistrarSettings(get_class($RegistrarA));
            $settingsB = $RegistrarHandler->getRegistrarSettings(get_class($RegistrarB));
            $displayPositionA = (int)$settingsA['displayPosition'];
            $displayPositionB = (int)$settingsB['displayPosition'];

            return $displayPositionA - $displayPositionB;
        });

        if (!empty($_REQUEST['registrar'])) {
            $Registrar = $RegistrarHandler->getRegistrarByHash($_REQUEST['registrar']);

            if ($Registrar) {
                $Engine->assign([
                    'fireUserActivationEvent' => true,
                    'User' => QUI::getUserBySession(),
                    'registrarHash' => $Registrar->getHash(),
                    'registrarType' => str_replace('\\', '\\\\', $Registrar->getType())
                ]);
            }
        }

        $Engine->assign([
            'Registrars' => $Registrars,
            'Registrar' => $CurrentRegistrar,
            'success' => $success,
            'redirectUrl' => $redirectUrl,
            'instantRedirect' => $instantRedirect,
            'instantReload' => $instantReload,
            'Login' => $Login,
            'termsOfUseLabel' => $termsOfUseLabel,
            'termsOfUseRequired' => $termsOfUseRequired,
            'termsOfUseAcctepted' => !empty($_POST['termsOfUseAccepted']),
            'registrationId' => $this->id,
            'showRegistrarTitle' => $this->getAttribute('showRegistrarTitle'),
            'nextLinksText' => $success ? RegistrationUtils::getFurtherLinksText() : false
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Registration.html');
    }

    /**
     * Get all Registrars that are displayed
     *
     * @return RegistrarCollection
     */
    protected function getRegistrars(): RegistrarCollection
    {
        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
        $filterRegistrars = $this->getAttribute('registrars');
        $Registrars = $RegistrarHandler->getRegistrars();

        if (empty($filterRegistrars)) {
            return $Registrars;
        }

        $registrars = $Registrars->toArray();
        $FilteredRegistrars = new RegistrarCollection();

        $registrars = array_filter($registrars, function ($Registrar) use ($filterRegistrars) {
            /** @var QUI\FrontendUsers\RegistrarInterface $Registrar */
            return in_array($Registrar->getType(), $filterRegistrars);
        });

        foreach ($registrars as $Registrar) {
            $FilteredRegistrars->append($Registrar);
        }

        return $FilteredRegistrars;
    }

    /**
     * Get the user that registered in this instance
     *
     * @return QUI\Interfaces\Users\User|null
     */
    public function getRegisteredUser(): ?QUI\Interfaces\Users\User
    {
        return $this->RegisteredUser;
    }

    /**
     * Is registration started?
     *
     * @return bool|QUI\FrontendUsers\RegistrarInterface
     */
    protected function isCurrentlyExecuted(): bool|QUI\FrontendUsers\RegistrarInterface
    {
        $FrontendUsers = QUI\FrontendUsers\Handler::getInstance();
        $Registrar = $this->getAttribute('Registrar');

        if ($Registrar instanceof QUI\FrontendUsers\RegistrarInterface && $Registrar->isActive()) {
            return $Registrar;
        }

        if (empty($_REQUEST['registrar'])) {
            return false;
        }

        $Registrar = $FrontendUsers->getRegistrarByHash($_REQUEST['registrar']);

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
     * @throws QUI\FrontendUsers\Exception\UserAlreadyExistsException
     * @throws QUI\FrontendUsers\Exception
     * @throws QUI\Exception
     */
    public function register()
    {
        if (!isset($_POST['registration'])) {
            return QUI\FrontendUsers\Handler::REGISTRATION_STATUS_ERROR;
        }

        $Registrar = $this->isCurrentlyExecuted();

        if ($Registrar === false) {
            throw new QUI\FrontendUsers\Exception([
                'quiqqer/frontend-users',
                'exception.registration.registrar_not_found'
            ]);
        }

        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();
        $Project = QUI::getRewrite()->getProject();
        $Registrar->setProject($Project);
        $Registrar->setAttributes($_POST);

        // check Terms Of Use
        if (
            !empty($registrationSettings['termsOfUseRequired'])
            && empty($_POST['termsOfUseAccepted'])
        ) {
            throw new QUI\FrontendUsers\Exception([
                'quiqqer/frontend-users',
                'exception.registration.terms_of_use_not_accepted'
            ]);
        }

        // Set validation settings
        $Registrar->setAttributes([
            'addressValidation' => $this->getAttribute('addressValidation')
        ]);

        $Registrar->validate();

        // Check user data
        $username = $Registrar->getUsername();

        if (mb_strlen($username) > 50) {
            throw new QUI\FrontendUsers\Exception([
                'quiqqer/frontend-users',
                'exception.registration.username_too_long',
                [
                    'maxLength' => 50
                ]
            ]);
        }

        $Registrar->checkUserAttributes();

        // Check if user already exists
        if (QUI::getUsers()->usernameExists($username)) {
            throw new QUI\FrontendUsers\Exception\UserAlreadyExistsException();
        }

        // Create user if everything is valid
        $NewUser = $Registrar->createUser();

        // add user to default groups
        $defaultGroups = explode(",", $registrationSettings['defaultGroups']);

        foreach ($defaultGroups as $groupId) {
            $NewUser->addToGroup($groupId);
        }

        // set registration/registrar data to user
        $NewUser->setAttributes([
            $RegistrarHandler::USER_ATTR_REGISTRATION_PROJECT => $Project->getName(),
            $RegistrarHandler::USER_ATTR_REGISTRATION_PROJECT_LANG => $Project->getLang(),
            $RegistrarHandler::USER_ATTR_REGISTRAR => $Registrar->getType(),
            $RegistrarHandler::USER_ATTR_USER_ACTIVATION_REQUIRED => true
        ]);

        // handle onRegistered from Registrar
        $Registrar->onRegistered($NewUser);
        $settings = $RegistrarHandler->getRegistrationSettings();
        $registrarSettings = $RegistrarHandler->getRegistrarSettings($Registrar->getType());

        // determine if the user has to set a new password on first login
        if ($settings['forcePasswordReset']) {
            $NewUser->setAttribute('quiqqer.set.new.password', true);
        }

        // send registration notice to admins
        $RegistrarHandler->sendRegistrationNotice($NewUser, $Registrar->getProject());
        $NewUser->save(QUI::getUsers()->getSystemUser());

        // check if the user has a password
        $result = QUI::getDataBase()->fetch([
            'select' => 'password',
            'from' => QUI::getDBTableName('users'),
            'where' => [
                'uuid' => $NewUser->getUUID()
            ],
            'limit' => 1
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
            case $RegistrarHandler::ACTIVATION_MODE_AUTO_WITH_EMAIL_CONFIRM:
                if (!$NewUser->isActive()) {
                    $NewUser->activate('', $SystemUser);
                }

                if ($registrarSettings['activationMode'] == $RegistrarHandler::ACTIVATION_MODE_AUTO_WITH_EMAIL_CONFIRM) {
                    $RegistrarHandler->sendEmailConfirmationMail(
                        $NewUser,
                        $NewUser->getAttribute('email'),
                        $Registrar->getProject()
                    );
                }
                break;
        }

        $this->RegisteredUser = $NewUser;

        QUI::getEvents()->fireEvent('quiqqerFrontendUsersUserRegister', [$NewUser, $Registrar, $registrationStatus]);

        return $registrationStatus;
    }
}
