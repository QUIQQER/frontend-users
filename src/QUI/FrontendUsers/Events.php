<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Users\User;
use QUI\Verification\Verifier;

/**
 * Class Events
 *
 * General events class for quiqqer/frontend-users
 *
 * @package QUI\FrontendUsers
 */
class Events
{
    /**
     * quiqqer/quiqqer: onUserActivate
     *
     * @param \QUI\Users\User $User
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function onUserActivate(User $User)
    {
        self::sendWelcomeMail($User);
        self::autoLogin($User);

        if ($User->getAttribute(Handler::USER_ATTR_USER_ACTIVATION_REQUIRED)) {
            $User->setAttribute(Handler::USER_ATTR_USER_ACTIVATION_REQUIRED, false);
            $User->save(QUI::getUsers()->getSystemUser());
        }
    }

    /**
     * quiqqer/quiqqer: onSiteInit
     *
     * @param QUI\Projects\Site $Site
     */
    public static function onSiteInit($Site)
    {
        switch ($Site->getAttribute('type')) {
            case Handler::SITE_TYPE_REGISTRATION:
            case Handler::SITE_TYPE_PROFILE:
            case Handler::SITE_TYPE_LOGIN:
                $Site->setAttribute('nocache', 1);
                break;
        }
    }

    /**
     * @param \QUI\Projects\Site\Edit $Site
     */
    public static function onSiteSave($Site)
    {
        // register path
        if ($Site->getAttribute('active') &&
            $Site->getAttribute('type') == 'quiqqer/frontend-users:types/profile'
        ) {
            $url = $Site->getLocation();
            $url = str_replace(QUI\Rewrite::URL_DEFAULT_SUFFIX, '', $url);

            QUI::getRewrite()->registerPath($url.'/*', $Site);
        }
    }

    /**
     * Send welcome mail to the user
     *
     * @param User $User
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function sendWelcomeMail(User $User)
    {
        $Handler              = Handler::getInstance();
        $registrationSettings = $Handler->getRegistrationSettings();

        if (!$registrationSettings['userWelcomeMail']
            || $User->getAttribute($Handler::USER_ATTR_WELCOME_MAIL_SENT)) {
            return;
        }

        // check if user registered himself
        $project     = $User->getAttribute($Handler::USER_ATTR_REGISTRATION_PROJECT);
        $projectLang = $User->getAttribute($Handler::USER_ATTR_REGISTRATION_PROJECT_LANG);

        // if no project data was set to the user this means the user
        // was created manually (by an administrator)
        if (empty($project) || empty($projectLang)) {
            return;
        }

        // set random password
        $randomPass = null;
        $Registrar  = $Handler->getReigstrarByUser($User);

        if (!$Registrar) {
            return;
        }

        if (boolval($registrationSettings['sendPassword'])
            && $Registrar->canSendPassword()) {
            $randomPass = QUI\Security\Password::generateRandom();
            $User->setPassword($randomPass, QUI::getUsers()->getSystemUser());
            $User->save(QUI::getUsers()->getSystemUser());
        }

        // send welcome mail
        $Project = QUI::getProjectManager()->getProject($project, $projectLang);
        $Handler->sendWelcomeMail($User, $Project, $randomPass);
    }

    /**
     * Auto-login user
     *
     * @param User $User
     * @param bool $checkEligibility (optional) - Checks if the user is eligible for auto login
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function autoLogin(User $User, $checkEligibility = true)
    {
        $Handler = Handler::getInstance();

        if ($checkEligibility) {
            $registrar = $User->getAttribute($Handler::USER_ATTR_REGISTRAR);

            if (empty($registrar)) {
                return;
            }

            // check if Registrar exists
            try {
                $Registrar = $Handler->getRegistrar($registrar);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                return;
            }

            $registrationSettings = $Handler->getRegistrationSettings();

            // do not log in if autoLogin is deactivated or user is already logged in!
            if (!$registrationSettings['autoLoginOnActivation']
                || QUI::getUserBySession()->getId()
                || $User->getAttribute($Handler::USER_ATTR_ACTIVATION_LOGIN_EXECUTED)) {
                return;
            }

            $settings = $Handler->getRegistrarSettings($Registrar->getType());

            // do not log in if users have to be manually activated
            if ($settings['activationMode'] === $Handler::ACTIVATION_MODE_MANUAL) {
                return;
            }
        }

        // login
        $secHash = QUI::getUsers()->getSecHash();

        $User->setAttributes([
            $Handler::USER_ATTR_ACTIVATION_LOGIN_EXECUTED => true
        ]);

        $User->save(QUI::getUsers()->getSystemUser());

        $Session = QUI::getSession();
        $Session->set('uid', $User->getId());
        $Session->set('auth', 1);
        $Session->set('secHash', $secHash);

        $useragent = '';

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $useragent = $_SERVER['HTTP_USER_AGENT'];
        }

        QUI::getDataBase()->update(
            QUI::getUsers()->table(),
            [
                'lastvisit'  => time(),
                'user_agent' => $useragent,
                'secHash'    => $secHash
            ],
            ['id' => $User->getId()]
        );
    }

    /**
     * quiqqer/quiqqer: onUserCreate
     *
     * @param User $User
     * @return void
     * @throws QUI\Exception
     */
    public static function onUserCreate(User $User)
    {
        $Conf                     = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $userGravatarDefaultValue = $Conf->get('userProfile', 'useGravatarUserDefaultValue');

        $User->setAttribute('quiqqer.frontendUsers.useGravatarIcon', $userGravatarDefaultValue);
        $User->save(QUI::getUsers()->getSystemUser());
    }

    /**
     * quiqqer/quiqqer: onUserDelete
     *
     * @param \QUI\Users\User $User
     * @return void
     */
    public static function onUserDelete(User $User)
    {
        // delete Verification for user (if not yet deleted by quiqqer/verification cron)
        try {
            $Verification = Verifier::getVerificationByIdentifier(
                $User->getId(),
                ActivationVerification::getType()
            );

            Verifier::removeVerification($Verification);
        } catch (\Exception $Exception) {
            // nothing -> if Verification not found it does not have to be deleted
        }
    }

    /**
     * quiqqer/quiqqer: onPackageSetup
     *
     * @param QUI\Package\Package $Package
     * @return void
     * @throws QUI\Exception
     */
    public static function onPackageSetup(QUI\Package\Package $Package)
    {
        if ($Package->getName() !== 'quiqqer/frontend-users') {
            return;
        }

        self::setAddressDefaultSettings();
        self::setRegistrarsDefaultSettings();
        self::createProfileCategoryViewPermissions();
    }

    /**
     * Set default settings for all registrars
     *
     * @return void
     */
    protected static function setRegistrarsDefaultSettings()
    {
        $RegistrarHandler = Handler::getInstance();
        $settings         = $RegistrarHandler->getRegistrarSettings();

        /** @var RegistrarInterface $Registrar */
        foreach ($RegistrarHandler->getAvailableRegistrars() as $Registrar) {
            $name = $Registrar->getType();

            if (!isset($settings[$name])) {
                $settings[$name] = [
                    'active'          => $name === QUI\FrontendUsers\Registrars\Email\Registrar::class,
                    'activationMode'  => 'mail',
                    'displayPosition' => 1
                ];
            }
        }

        $RegistrarHandler->setRegistrarSettings($settings);
    }

    /**
     * Set address fields default settings
     *
     * @return void
     */
    protected static function setAddressDefaultSettings()
    {
        $Conf          = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $addressFields = $Conf->getValue('registration', 'addressFields');

        // do not set default settings if manual settings have already been set
        if (!empty($addressFields)) {
            return;
        }

        $addressFields = [
            'salutation' => [
                'show'     => true,
                'required' => false
            ],
            'firstname'  => [
                'show'     => true,
                'required' => true
            ],
            'lastname'   => [
                'show'     => true,
                'required' => true
            ],
            'street_no'  => [
                'show'     => true,
                'required' => true
            ],
            'zip'        => [
                'show'     => true,
                'required' => true
            ],
            'city'       => [
                'show'     => true,
                'required' => true
            ],
            'country'    => [
                'show'     => true,
                'required' => true
            ],
            'company'    => [
                'show'     => true,
                'required' => false
            ],
            'phone'      => [
                'show'     => true,
                'required' => false
            ]
        ];

        $Conf->setValue('registration', 'addressFields', json_encode($addressFields));
        $Conf->save();
    }

    /**
     * quiqqer/quiqqer: onTemplateGetHeader
     *
     * @param QUI\Template $TemplateManager
     */
    public static function onTemplateGetHeader(QUI\Template $TemplateManager)
    {
        $cssFile = URL_OPT_DIR.'quiqqer/frontend-users/bin/style.css';
        $TemplateManager->extendHeader('<link rel="stylesheet" type="text/css" href="'.$cssFile.'">');

        $User = QUI::getUserBySession();

        echo "<script>
            (function() {
                var registerNewLogin = function() {
                    require(['qui/QUI'], function(QUI) {
                        QUI.addEvent('onAjaxLogin', function(QUIAjax, call, method, callback, params) {
                            require(['package/quiqqer/frontend-users/bin/frontend/controls/login/Window'], function(Window) {
                                new Window({
                                    reload: false,
                                    events: {
                                        onCancel: function() {
                                            window.location.reload();
                                        },
                                        
                                        onSuccess: function() {
                                            QUIAjax.request(call, method, callback, params);
                                        }
                                    }
                                }).open();
                            });
                            
                            return true;
                        });
                    });
                };
                
                var waitForRequireEventRegister = setInterval(function() {
                    if (typeof require === 'undefined') {
                        return;
                    }
                    
                    clearInterval(waitForRequireEventRegister);
                    
                    var loadQUI = function () {
                        return Promise.resolve();
                    };
            
                    if (typeof whenQuiLoaded === 'function') {
                        loadQUI = whenQuiLoaded;
                    }
                    
                    loadQUI().then(registerNewLogin);
                }, 200);
            })();
        </script>";

        if (!$User->getAttribute('quiqqer.set.new.password')) {
            return;
        }

        echo "<script>
            (function() {
                var openChangePasswordWindow = function() {
                    require([
                        'controls/users/password/Window',
                        'Locale'
                    ], function(Password, QUILocale) {
                        new Password({
                            uid: '".$User->getId()."',
                            mustChange: true,
                            message: QUILocale.get('quiqqer/quiqqer', 'message.set.new.password'),
                            events: {
                                onSuccess: function() {
                                    window.location.reload();
                                }
                            }
                        }).open();
                    });
                };
           
                var checkChangePasswordWindow = function() {
                    require(['Locale'], function(QUILocale) {
                        if (!QUILocale.exists('quiqqer/quiqqer', 'message.set.new.password')) {
                            (function() {
                                openChangePasswordWindow();
                            }).delay(2000);
                            return;
                        }
                        
                        openChangePasswordWindow();
                    });            
                };
                
                var waitForRequire = setInterval(function() {
                    if (typeof require === 'undefined') {
                        return;
                    }
                    
                    clearInterval(waitForRequire);
                    checkChangePasswordWindow();
                }, 200);
            })();
        </script>";
    }

    /**
     * Create view permissions for all Profile categories
     *
     * @return void
     * @throws QUI\Exception
     */
    protected static function createProfileCategoryViewPermissions()
    {
        $Permissions           = new QUI\Permissions\Manager();
        $permissionPrefix      = 'quiqqer.frontendUsers.profile.view.';
        $defaultViewPermission = (int)QUI::getPackage('quiqqer/frontend-users')->getConfig()->get(
            'user_profile',
            'categoryViewDefaultPermission'
        );

        foreach (Utils::getProfileCategories() as $c) {
            foreach ($c['items'] as $setting) {
                $permission = $permissionPrefix.$c['name'].'.'.$setting['name'];

                try {
                    $Permissions->getPermissionData($permission);
                    continue;
                } catch (\Exception $Exception) {
                    // if permission does not exist -> create it
                }

                $title = $permission;

                if (!empty($setting['title'])) {
                    if (is_string($setting['title'])) {
                        $title = $setting['title'];
                    } elseif (is_array($setting['title']) && count($setting['title']) === 2) {
                        $title = $setting['title'][0].' '.$setting['title'][1];
                    }
                }

                $Permissions->addPermission([
                    'name'         => $permission,
                    'title'        => $title,
                    'desc'         => '',
                    'type'         => 'bool',
                    'area'         => '',
                    'src'          => 'quiqqer/frontend-users',
                    'defaultvalue' => $defaultViewPermission
                ]);
            }
        }
    }
}
