<?php

/**
 * This file contains QUI\FrontendUsers\Handler
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\Utils\Singleton;
use QUI\Verification\Verifier;
use QUI\Mail\Mailer;

/**
 * Class Registration Handling
 * - Main Registration Handler
 *
 * @package QUI\FrontendUsers
 */
class Handler extends Singleton
{
    /**
     * Registration statuses
     */
    const REGISTRATION_STATUS_ERROR   = 0;
    const REGISTRATION_STATUS_SUCCESS = 1;
    const REGISTRATION_STATUS_PENDING = 2;

    /**
     * Activation modes
     */
    const ACTIVATION_MODE_MAIL   = 'mail';
    const ACTIVATION_MODE_AUTO   = 'auto';
    const ACTIVATION_MODE_MANUAL = 'manual';

    /**
     * Password input types
     */
    const PASSWORD_INPUT_DEFAULT  = 'default';
    const PASSWORD_INPUT_VALIDATE = 'validation';
    const PASSWORD_INPUT_NONE     = 'none';

    /**
     * Username input types
     */
    const USERNAME_INPUT_NONE     = 'none';
    const USERNAME_INPUT_OPTIONAL = 'optional';
    const USERNAME_INPUT_REQUIRED = 'required';

    /**
     * Full name input types
     */
    const FULLNAME_INPUT_NONE               = 'none';
    const FULLNAME_INPUT_FIRSTNAME_OPTIONAL = 'firstname_optional';
    const FULLNAME_INPUT_FIRSTNAME_REQUIRED = 'firstname_required';
    const FULLNAME_INPUT_FULLNAME_OPTIONAL  = 'fullname_optional';
    const FULLNAME_INPUT_FULLNAME_REQUIRED  = 'fullname_required';

    /**
     * Site types
     */
    const SITE_TYPE_REGISTRATION        = 'quiqqer/frontend-users:types/registration';
    const SITE_TYPE_REGISTRATION_SIGNUP = 'quiqqer/frontend-users:types/registrationSignUp';
    const SITE_TYPE_LOGIN               = 'quiqqer/frontend-users:types/login';
    const SITE_TYPE_PROFILE             = 'quiqqer/frontend-users:types/profile';

    /**
     * User attributes
     */
    const USER_ATTR_WELCOME_MAIL_SENT         = 'quiqqer.frontendUsers.welcomeMailSent';
    const USER_ATTR_REGISTRATION_PROJECT      = 'quiqqer.frontendUsers.registrationProject';
    const USER_ATTR_REGISTRATION_PROJECT_LANG = 'quiqqer.frontendUsers.registrationProjectLang';
    const USER_ATTR_REGISTRAR                 = 'quiqqer.frontendUsers.registrar';
    const USER_ATTR_ACTIVATION_LOGIN_EXECUTED = 'quiqqer.frontendUsers.activationLoginExecuted';
    const USER_ATTR_EMAIL_VERIFIED            = 'quiqqer.frontendUsers.emailVerified';
    const USER_ATTR_USER_ACTIVATION_REQUIRED  = 'quiqqer.frontendUsers.userActivationRequired';

    /**
     * Misc
     */
    const SESSION_REGISTRAR = 'frontend_users_registrar';

    /**
     * @var null|RegistrarCollection
     */
    protected $Registrar = null;

    /**
     * Registration IDs of the current runtime
     *
     * @var array
     */
    protected $registrationIds = [];

    /**
     * Handler constructor.
     */
    public function __construct()
    {
        $this->Registrar = new RegistrarCollection();
    }

    /**
     * @return RegistrarCollection
     */
    public function getRegistrars()
    {
        $Registrars        = new RegistrarCollection();
        $Available         = $this->getAvailableRegistrars();
        $registrarSettings = $this->getRegistrarSettings();

        /** @var RegistrarInterface $Registrar */
        foreach ($Available as $Registrar) {
            $t = $Registrar->getType();

            if (isset($registrarSettings[$t])) {
                if (!$Registrar->isActive()) {
                    continue;
                }
            } else {
                continue;
            }

            $Registrars->append($Registrar);
        }

        return $Registrars;
    }

    /**
     * Get ACTIVE Registrar
     *
     * @param string $registrar - Registrar type
     * @return false|RegistrarInterface
     */
    public function getRegistrar($registrar)
    {
        /** @var RegistrarInterface $Registrar */
        foreach ($this->getAvailableRegistrars() as $Registrar) {
            if ($Registrar->getType() === $registrar) {
                return $Registrar;
            }
        }

        return false;
    }

    /**
     * Get ACTIVE Registrar by user
     *
     * @param QUI\Users\User $User
     * @return RegistrarInterface|false
     */
    public function getReigstrarByUser(QUI\Users\User $User)
    {
        $registrar = $User->getAttribute(self::USER_ATTR_REGISTRAR);

        if (empty($registrar)) {
            return false;
        }

        return self::getRegistrar($registrar);
    }

    /**
     * Get ACTIVE Registrar by hash
     *
     * @param string $hash
     * @return false|RegistrarInterface
     */
    public function getRegistrarByHash($hash)
    {
        /** @var RegistrarInterface $Registrar */
        foreach ($this->getAvailableRegistrars() as $Registrar) {
            if ($Registrar->getHash() === $hash) {
                return $Registrar;
            }
        }

        return false;
    }

    /**
     * Return all available registrar
     *
     * @return RegistrarCollection
     */
    public function getAvailableRegistrars()
    {
        if ($this->Registrar->isNotEmpty()) {
            return $this->Registrar;
        }

        $list      = [];
        $installed = QUI::getPackageManager()->getInstalled();

        foreach ($installed as $package) {
            try {
                $Package = QUI::getPackage($package['name']);

                if (!$Package->isQuiqqerPackage()) {
                    continue;
                }

                $list = array_merge($list, $Package->getProvider('registrar'));
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        // default registrar has to be first
        usort($list, function ($a, $b) {
            if ($a === '\\'.Registrars\Email\Registrar::class) {
                return -1;
            }

            if ($b === '\\'.Registrars\Email\Registrar::class) {
                return 1;
            }

            return 0;
        });

        foreach ($list as $provider) {
            try {
                if (!class_exists($provider)) {
                    continue;
                }

                $this->Registrar->append(new $provider());
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $this->Registrar;
    }

    /**
     * Get all settings for user profile
     *
     * @return array
     * @throws QUI\Exception
     */
    public function getUserProfileSettings()
    {
        $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();

        return $Conf->getSection('userProfile');
    }

    /**
     * Get all settings for user bar
     *
     * @return array
     * @throws QUI\Exception
     */
    public function getProfileBarSettings()
    {
        $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();

        return $Conf->getSection('profileBar');
    }

    /**
     * Get registration settings concerning all Registars alike
     *
     * @return array
     * @throws QUI\Exception
     */
    public function getRegistrationSettings()
    {
        $Conf     = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $settings = $Conf->getSection('registration');

        if (!empty($settings['termsOfUseSite'])) {
            $settings['termsOfUseSite'] = json_decode($settings['termsOfUseSite'], true);
        }

        if (!empty($settings['privacyPolicySite'])) {
            $settings['privacyPolicySite'] = json_decode($settings['privacyPolicySite'], true);
        }

        if (!empty($settings['autoRedirectOnSuccess'])) {
            $settings['autoRedirectOnSuccess'] = json_decode($settings['autoRedirectOnSuccess'], true);
        }

        return $settings;
    }

    /**
     * Get login settings
     *
     * @return array
     * @throws QUI\Exception
     */
    public function getLoginSettings()
    {
        $Conf     = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $settings = $Conf->getSection('login');

        $settings['redirectOnLogin'] = json_decode($settings['redirectOnLogin'], true);

        if (empty($settings['authenticators'])) {
            $settings['authenticators'] = [];
        } else {
            $settings['authenticators'] = json_decode($settings['authenticators'], true);
        }

        return $settings;
    }

    /**
     * Get address field settings
     *
     * @return array
     * @throws QUI\Exception
     */
    public function getAddressFieldSettings()
    {
        $registrationSettings = $this->getRegistrationSettings();

        return json_decode($registrationSettings['addressFields'], true);
    }

    /**
     * Get settings for mail
     *
     * @return array
     * @throws QUI\Exception
     */
    public function getMailSettings()
    {
        $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();

        return $Conf->getSection('mail');
    }

    /**
     * Get settings for one or all Registrars
     *
     * @param string $registrarClass (optional) - Registar class path (namespace)
     * @return array
     */
    public function getRegistrarSettings($registrarClass = null)
    {
        try {
            $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());

            return [];
        }


        $registrarSettings = $Conf->get('registrars', 'registrarSettings');

        if (empty($registrarSettings)) {
            return [];
        }

        $registrarSettings = json_decode($registrarSettings, true);

        foreach ($registrarSettings as $type => $settings) {
            unset($registrarSettings[$type]);

            $type                     = base64_decode($type);
            $registrarSettings[$type] = $settings;
        }

        if (!is_null($registrarClass)
            && isset($registrarSettings[$registrarClass])) {
            return $registrarSettings[$registrarClass];
        }

        return $registrarSettings;
    }

    /**
     * Set settings for registrars
     *
     * @param array $settings
     * @return void
     * @throws QUI\Exception
     */
    public function setRegistrarSettings($settings)
    {
        $Conf          = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $writeSettings = [];

        foreach ($settings as $registrarType => $settingsData) {
            $writeSettings[base64_encode($registrarType)] = $settingsData;
        }

        $Conf->set('registrars', 'registrarSettings', json_encode($writeSettings));
        $Conf->save();
    }

    /**
     * Send activtion mail for a user account
     *
     * @param QUI\Users\User $User
     * @param RegistrarInterface $Registrar
     * @return bool - success
     * @throws QUI\Exception
     */
    public function sendActivationMail(QUI\Users\User $User, RegistrarInterface $Registrar)
    {
        $Project = $Registrar->getProject();

        $ActivationVerification = new ActivationVerification($User->getId(), [
            'project'     => $Project->getName(),
            'projectLang' => $Project->getLang(),
            'registrar'   => $Registrar->getHash()
        ]);

        $activationLink = Verifier::startVerification($ActivationVerification, true);

        $L      = QUI::getLocale();
        $lg     = 'quiqqer/frontend-users';
        $tplDir = QUI::getPackage('quiqqer/frontend-users')->getDir().'templates/';
        $host   = $Project->getVHost();

        try {
            $this->sendMail(
                [
                    'subject' => $L->get($lg, 'mail.registration_activation.subject', [
                        'host' => $host
                    ])
                ],
                [
                    $User->getAttribute('email')
                ],
                $tplDir.'mail.registration_activation.html',
                [
                    'body' => $L->get($lg, 'mail.registration_activation.body', [
                        'host'           => $host,
                        'userId'         => $User->getId(),
                        'username'       => $User->getUsername(),
                        'userFirstName'  => $User->getAttribute('firstname') ?: '',
                        'userLastName'   => $User->getAttribute('lastname') ?: '',
                        'email'          => $User->getAttribute('email'),
                        'date'           => $L->formatDate(time()),
                        'activationLink' => $activationLink
                    ])
                ]
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class.' :: sendActivationMail -> Send mail failed'
            );

            QUI\System\Log::writeException($Exception);

            return false;
        }

        return true;
    }

    /**
     * Send welcome mail to user upon activation
     *
     * @param QUI\Users\User $User
     * @param QUI\Projects\Project $Project
     * @param string $userPassword (optional) - send user password
     * @return void
     * @throws QUI\Exception
     */
    public function sendWelcomeMail(QUI\Users\User $User, QUI\Projects\Project $Project, $userPassword = null)
    {
        $L      = QUI::getLocale();
        $lg     = 'quiqqer/frontend-users';
        $tplDir = QUI::getPackage('quiqqer/frontend-users')->getDir().'templates/';
        $host   = $Project->getVHost();

        $LoginSite = $this->getLoginSite($Project);
        $loginUrl  = $Project->getVHost(true);

        if ($LoginSite) {
            $loginUrl = $LoginSite->getUrlRewritten();
        }

        try {
            $this->sendMail(
                [
                    'subject' => $L->get($lg, 'mail.registration_welcome.subject', [
                        'host' => $host
                    ])
                ],
                [
                    $User->getAttribute('email')
                ],
                $tplDir.'mail.registration_welcome.html',
                [
                    'body' => $L->get($lg, 'mail.registration_welcome.body', [
                        'host'          => $host,
                        'username'      => $User->getUsername(),
                        'userFirstName' => $User->getAttribute('firstname') ?: '',
                        'userLastName'  => $User->getAttribute('lastname') ?: '',
                        'loginUrl'      => $loginUrl,
                        'userPassword'  => is_null($userPassword) ? ''
                            : $L->get($lg, 'mail.registration_welcome.body.password', [
                                'username' => $User->getUsername(),
                                'password' => $userPassword
                            ])
                    ])
                ]
            );

            // set "welcome mail sent"-flag to user so it won't be sent again
            $User->setAttribute(Handler::USER_ATTR_WELCOME_MAIL_SENT, true);
            $User->save(QUI::getUsers()->getSystemUser());
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class.' :: sendWelcomeMail -> Send mail failed'
            );

            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Send information about a new registration
     *
     * @param QUI\Users\User $User
     * @param QUI\Projects\Project $Project
     * @return void
     * @throws QUI\Exception
     */
    public function sendRegistrationNotice(QUI\Users\User $User, QUI\Projects\Project $Project)
    {
        $registrationSettings = $this->getRegistrationSettings();

        if (empty($registrationSettings['sendInfoMailOnRegistrationTo'])) {
            return;
        }

        $recipients = explode(",", $registrationSettings['sendInfoMailOnRegistrationTo']);

        $L      = QUI::getLocale();
        $lg     = 'quiqqer/frontend-users';
        $tplDir = QUI::getPackage('quiqqer/frontend-users')->getDir().'templates/';
        $host   = $Project->getVHost();

        $Registrar = self::getRegistrar(
            $User->getAttribute(self::USER_ATTR_REGISTRAR)
        );

        try {
            $this->sendMail(
                [
                    'subject' => $L->get($lg, 'mail.registration_notice.subject', [
                        'host' => $host
                    ])
                ],
                $recipients,
                $tplDir.'mail.registration_notice.html',
                [
                    'body' => $L->get($lg, 'mail.registration_notice.body', [
                        'host'      => $host,
                        'userId'    => $User->getId(),
                        'username'  => $User->getUsername(),
                        'email'     => $User->getAttribute('email'),
                        'date'      => $L->formatDate(time()),
                        'registrar' => $Registrar ? $Registrar->getTitle() : '-'
                    ])
                ]
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class.' :: sendRegistrationNotice -> Send mail failed'
            );

            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Send activtion mail for a user account
     *
     * @param QUI\Users\User $User
     * @param string $newEmail - New E-Mail-Adress
     * @param QUI\Projects\Project $Project - The QUIQQER Project where the change action took place
     * @return void
     * @throws QUI\Exception
     */
    public function sendChangeEmailAddressMail(QUI\Users\User $User, $newEmail, $Project)
    {
        $EmailConfirmVerification = new EmailConfirmVerification($User->getId(), [
            'project'     => $Project->getName(),
            'projectLang' => $Project->getLang(),
            'newEmail'    => $newEmail
        ]);

        $confirmLink = Verifier::startVerification($EmailConfirmVerification, true);

        $L      = QUI::getLocale();
        $lg     = 'quiqqer/frontend-users';
        $tplDir = QUI::getPackage('quiqqer/frontend-users')->getDir().'templates/';
        $host   = $Project->getVHost();

        try {
            $this->sendMail(
                [
                    'subject' => $L->get($lg, 'mail.change_email_address.subject')
                ],
                [
                    $newEmail
                ],
                $tplDir.'mail.change_email_address.html',
                [
                    'body' => $L->get($lg, 'mail.change_email_address.body', [
                        'host'          => $host,
                        'userId'        => $User->getId(),
                        'username'      => $User->getUsername(),
                        'userFirstName' => $User->getAttribute('firstname') ?: '',
                        'userLastName'  => $User->getAttribute('lastname') ?: '',
                        'newEmail'      => $newEmail,
                        'date'          => $L->formatDate(time()),
                        'confirmLink'   => $confirmLink
                    ])
                ]
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class.' :: sendChangeEmailAddressMail -> Send mail failed'
            );

            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Send verification mail for user account deletion
     *
     * @param QUI\Users\User $User
     * @param QUI\Projects\Project $Project - The QUIQQER Project where the change action took place
     * @return void
     *
     * @throws QUI\Verification\Exception
     * @throws QUI\Exception
     */
    public function sendDeleteUserConfirmationMail(QUI\Users\User $User, $Project)
    {
        $DeleteUserVerification = new UserDeleteConfirmVerification($User->getId(), [
            'project'     => $Project->getName(),
            'projectLang' => $Project->getLang()
        ]);

        $confirmLink = Verifier::startVerification($DeleteUserVerification, true);

        $L      = QUI::getLocale();
        $lg     = 'quiqqer/frontend-users';
        $tplDir = QUI::getPackage('quiqqer/frontend-users')->getDir().'templates/';
        $host   = $Project->getVHost();

        try {
            $this->sendMail(
                [
                    'subject' => $L->get($lg, 'mail.delete_user_confirm.subject')
                ],
                [
                    $User->getAttribute('email')
                ],
                $tplDir.'mail.delete_user_confirm.html',
                [
                    'body' => $L->get($lg, 'mail.delete_user_confirm.body', [
                        'host'          => $host,
                        'userId'        => $User->getId(),
                        'username'      => $User->getUsername(),
                        'userFirstName' => $User->getAttribute('firstname') ?: '',
                        'userLastName'  => $User->getAttribute('lastname') ?: '',
                        'date'          => $L->formatDate(time()),
                        'confirmLink'   => $confirmLink
                    ])
                ]
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class.' :: sendDeleteUserConfirmationMail -> Send mail failed'
            );

            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Send an email to the frontend user
     *
     * @param array $mailData - mail data ("subject", "from", "fromName")
     * @param array $recipients - e-mail addresses
     * @param string $templateFile
     * @param array $templateVars (optional) - additional template variables (besides $this)
     * @return void
     *
     * @throws QUI\Exception
     */
    public function sendMail($mailData, $recipients, $templateFile, $templateVars = [])
    {
        if (empty($recipients)) {
            return;
        }

        $mailSettings = self::getMailSettings();
        $Engine       = QUI::getTemplateManager()->getEngine();

        $Engine->assign($templateVars);

        $template = $Engine->fetch($templateFile);
        $Mailer   = new Mailer();

        foreach ($recipients as $recipient) {
            $Mailer->addRecipient($recipient);
        }

        if (!empty($mailData['subject'])) {
            $Mailer->setSubject($mailData['subject']);
        }

        if (!empty($mailSettings['mailFromAddress'])) {
            $Mailer->setFrom($mailSettings['mailFromAddress']);
        }

        if (!empty($mailSettings['mailFromText'])) {
            $Mailer->setFromName($mailSettings['mailFromText']);
        }

        $Mailer->setBody($template);
        $Mailer->send();
    }

    /**
     * Get ACTIVE registration site for a project
     *
     * @param QUI\Projects\Project $Project (optional) - if omitted use default project
     * @return QUI\Projects\Site|false - Site object or false if no ACTIVE registration site found
     * @throws QUI\Exception
     */
    public function getRegistrationSite($Project = null)
    {
        if (is_null($Project)) {
            $Project = QUI::getProjectManager()->getStandard();
        }

        $result = $Project->getSites([
            'where' => [
                'type' => self::SITE_TYPE_REGISTRATION
            ],
            'limit' => 1
        ]);

        if (empty($result)) {
            return false;
        }

        return current($result);
    }

    /**
     * Get ACTIVE registration and signup site for a project
     *
     * @param QUI\Projects\Project $Project (optional) - if omitted use default project
     * @return QUI\Projects\Site|false - Site object or false if no ACTIVE registration site found
     * @throws QUI\Exception
     */
    public function getRegistrationSignUpSite($Project = null)
    {
        if (is_null($Project)) {
            $Project = QUI::getProjectManager()->getStandard();
        }

        $result = $Project->getSites([
            'where' => [
                'type' => self::SITE_TYPE_REGISTRATION_SIGNUP
            ],
            'limit' => 1
        ]);

        if (empty($result)) {
            return false;
        }

        return current($result);
    }

    /**
     * Get ACTIVE login site for a project
     *
     * @param QUI\Projects\Project $Project (optional) - if omitted use default project
     * @return QUI\Projects\Site|false - Site object or false if no ACTIVE login site found
     * @throws QUI\Exception
     */
    public function getLoginSite($Project = null)
    {
        if (is_null($Project)) {
            $Project = QUI::getProjectManager()->getStandard();
        }

        $result = $Project->getSites([
            'where' => [
                'type' => self::SITE_TYPE_LOGIN
            ],
            'limit' => 1
        ]);

        if (empty($result)) {
            return false;
        }

        return current($result);
    }

    /**
     * Get ACTIVE profile site for a project
     *
     * @param QUI\Projects\Project $Project (optional) - if omitted use default project
     * @return QUI\Projects\Site|false - Site object or false if no ACTIVE profile site found
     * @throws QUI\Exception
     */
    public function getProfileSite($Project = null)
    {
        if (is_null($Project)) {
            $Project = QUI::getProjectManager()->getStandard();
        }

        $result = $Project->getSites([
            'where' => [
                'type' => self::SITE_TYPE_PROFILE
            ],
            'limit' => 1
        ]);

        if (empty($result)) {
            return false;
        }

        return current($result);
    }

    /**
     * Get Site that is redirected to after activation success.
     *
     * @return false|QUI\Projects\Site
     */
    public function getRedirectOnActivationSite()
    {
        try {
            $registrationSettings = $this->getRegistrationSettings();
            $projectLang          = QUI::getRewrite()->getProject()->getLang();
            
            if (!empty($registrationSettings['autoRedirectOnSuccess'][$projectLang])) {
                return QUI\Projects\Site\Utils::getSiteByLink(
                    $registrationSettings['autoRedirectOnSuccess'][$projectLang]
                );
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return false;
    }

    /**
     * Checks the current configuration of quiqqer/frontend-users
     * and throws Exceptions if a misconfiguration is detected
     *
     * @return void
     * @throws Exception
     * @throws QUI\Exception
     */
    public function checkConfiguration()
    {
        $lg       = 'quiqqer/frontend-users';
        $settings = $this->getRegistrationSettings();

        if (boolval($settings['sendPassword'])
            && !(int)$settings['userWelcomeMail']) {
            throw new Exception([
                $lg,
                'exception.handler.check_config.no_welcome_mail_for_password_send'
            ]);
        }
    }

    /**
     * Check if users are allowed to set their own username
     *
     * @return bool
     * @throws QUI\Exception
     */
    public function isUsernameInputAllowed()
    {
        $settings = $this->getRegistrationSettings();

        return $settings['usernameInput'] !== self::USERNAME_INPUT_NONE;
    }

    /**
     * Create an ID with a sequential number to identify multiple Registration
     * controls on one Site
     *
     * @return string
     */
    public function createRegistrationId()
    {
        $registrationId          = 'registration-'.(count($this->registrationIds) + 1);
        $this->registrationIds[] = $registrationId;

        return $registrationId;
    }

    /**
     * Get max length for each user attribute
     *
     * @return array
     */
    public function getUserAttributeLengthRestrictions()
    {
        return [
            'firstname'  => 40,
            'lastname'   => 40,
            'email'      => 255,
            'salutation' => 10,
            'company'    => 100,
            'street_no'  => 200,
            'zip'        => 200,
            'city'       => 200,
            'country'    => 100,
            'phone'      => 200,
            'mobile'     => 200,
            'fax'        => 200,
            'password'   => 200,
            'username'   => 50
        ];
    }
}
