<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;
use QUI\Interfaces\Users\User;
use QUI\Smarty\Collector;
use QUI\Verification\VerificationRepository;

use function base64_encode;
use function json_encode;

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
     * quiqqer/core: onUserActivate
     *
     * @param User $User
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function onUserActivate(User $User): void
    {
        self::sendWelcomeMail($User);
        self::autoLogin($User);

        if ($User->getAttribute(Handler::USER_ATTR_USER_ACTIVATION_REQUIRED)) {
            $User->setAttribute(Handler::USER_ATTR_USER_ACTIVATION_REQUIRED, false);
            $User->save(QUI::getUsers()->getSystemUser());
        }
    }

    /**
     * quiqqer/core: onSiteInit
     *
     * @param QUI\Interfaces\Projects\Site $Site
     */
    public static function onSiteInit(QUI\Interfaces\Projects\Site $Site): void
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
     * @param QUI\Interfaces\Projects\Site $Site
     */
    public static function onSiteSave(QUI\Interfaces\Projects\Site $Site): void
    {
        // register path
        if (
            $Site->getAttribute('active')
            && $Site->getAttribute('type') == 'quiqqer/frontend-users:types/profile'
            && method_exists($Site, 'getLocation')
        ) {
            $url = $Site->getLocation();
            $url = str_replace(QUI\Rewrite::URL_DEFAULT_SUFFIX, '', $url);

            QUI::getRewrite()->registerPath($url . '/*', $Site);
        }
    }

    /**
     * Send welcome mail to the user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function sendWelcomeMail(QUI\Interfaces\Users\User $User): void
    {
        $Handler = Handler::getInstance();
        $registrationSettings = $Handler->getRegistrationSettings();

        if (
            !$registrationSettings['userWelcomeMail']
            || $User->getAttribute($Handler::USER_ATTR_WELCOME_MAIL_SENT)
        ) {
            return;
        }

        // check if user registered himself
        $project = $User->getAttribute($Handler::USER_ATTR_REGISTRATION_PROJECT);
        $projectLang = $User->getAttribute($Handler::USER_ATTR_REGISTRATION_PROJECT_LANG);

        // if no project data was set to the user this means the user
        // was created manually (by an administrator)
        if (empty($project) || empty($projectLang)) {
            return;
        }

        // set random password
        $randomPass = null;
        $Registrar = $Handler->getRegistrarByUser($User);

        if (!$Registrar) {
            return;
        }

        if ($registrationSettings['sendPassword'] && $Registrar->canSendPassword()) {
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
     * @param QUI\Interfaces\Users\User $User
     * @param bool $checkEligibility (optional) - Checks if the user is eligible for auto login
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function autoLogin(QUI\Interfaces\Users\User $User, bool $checkEligibility = true): void
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
            if (
                !$registrationSettings['autoLoginOnActivation']
                || QUI::getUserBySession()->getUUID()
                || $User->getAttribute($Handler::USER_ATTR_ACTIVATION_LOGIN_EXECUTED)
            ) {
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
        $Session->set('uid', $User->getUUID());
        $Session->set('auth', 1);
        $Session->set('secHash', $secHash);

        $useragent = '';

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $useragent = $_SERVER['HTTP_USER_AGENT'];
        }

        QUI::getDataBase()->update(
            QUI::getUsers()->table(),
            [
                'lastvisit' => time(),
                'user_agent' => $useragent,
                'secHash' => $secHash
            ],
            ['uuid' => $User->getUUID()]
        );

        QUI::getEvents()->fireEvent(
            'quiqqerFrontendUsersUserAutoLogin',
            [
                $User,
                $Registrar ?? false
            ]
        );
    }

    /**
     * quiqqer/core: onUserCreate
     *
     * @param QUI\Interfaces\Users\User $User
     * @return void
     * @throws QUI\Exception
     */
    public static function onUserCreate(QUI\Interfaces\Users\User $User): void
    {
        $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $userGravatarDefaultValue = $Conf->get('userProfile', 'useGravatarUserDefaultValue');

        $User->setAttribute('quiqqer.frontendUsers.useGravatarIcon', $userGravatarDefaultValue);
        $User->save(QUI::getUsers()->getSystemUser());
    }

    /**
     * quiqqer/core: onUserDelete
     *
     * @param User $User
     * @return void
     */
    public static function onUserDelete(User $User): void
    {
        // delete Verification for user (if not yet deleted by quiqqer/verification cron)
        try {
            $repository = new VerificationRepository();
            $verification = $repository->findByIdentifier(
                'activate-' . $User->getUUID()
            );

            // if Verification not found it does not have to be deleted
            if ($verification) {
                $repository->delete($verification);
            }
        } catch (\Throwable) {
            // nothing -> if Verification not found it does not have to be deleted
        }
    }

    /**
     * quiqqer/core: onPackageInstall
     *
     * @param QUI\Package\Package $Package
     * @return void
     */
    public static function onPackageInstall(QUI\Package\Package $Package): void
    {
        if ($Package->getName() !== 'quiqqer/frontend-users') {
            return;
        }

        try {
            self::setAddressDefaultSettings();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * quiqqer/core: onPackageSetup
     *
     * @param QUI\Package\Package $Package
     * @return void
     * @throws QUI\Exception
     */
    public static function onPackageSetup(QUI\Package\Package $Package): void
    {
        if ($Package->getName() !== 'quiqqer/frontend-users') {
            return;
        }

        // Clear cache
        QUI\Cache\Manager::clear('package/quiqqer/frontendUsers');

        self::setRegistrarsDefaultSettings();
        self::setAuthenticatorsDefaultSettings();
        self::createProfileCategoryViewPermissions();
        self::checkUserMediaFolder();
    }

    /**
     * Set default settings for all frontend authenticators
     *
     * @return void
     * @throws Exception
     */
    protected static function setAuthenticatorsDefaultSettings(): void
    {
        try {
            $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getSection('login');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        if (!empty($settings['authenticators'])) {
            return;
        }

        $settings['authenticators'] = [];

        foreach (QUI\Users\Auth\Handler::getInstance()->getAvailableAuthenticators() as $class) {
            // Some authenticators are always available and cannot be switched off
            if ($class == 'QUI\Users\Auth\QUIQQER') {
                continue;
            }

            $settings['authenticators'][base64_encode($class)] = true;
        }

        $Conf->setValue('login', 'authenticators', json_encode($settings['authenticators']));
        $Conf->save();
    }

    /**
     * Set default settings for all registrars
     *
     * @return void
     * @throws Exception
     */
    protected static function setRegistrarsDefaultSettings(): void
    {
        $RegistrarHandler = Handler::getInstance();
        $settings = $RegistrarHandler->getRegistrarSettings();

        /** @var RegistrarInterface $Registrar */
        foreach ($RegistrarHandler->getAvailableRegistrars() as $Registrar) {
            $name = $Registrar->getType();

            if (!isset($settings[$name])) {
                $settings[$name] = [
                    'active' => $name === QUI\FrontendUsers\Registrars\Email\Registrar::class,
                    'activationMode' => 'mail',
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
     * @throws QUI\Exception
     */
    protected static function setAddressDefaultSettings(): void
    {
        $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();

        $addressFields = [
            'salutation' => [
                'show' => true,
                'required' => false
            ],
            'firstname' => [
                'show' => true,
                'required' => true
            ],
            'lastname' => [
                'show' => true,
                'required' => true
            ],
            'street_no' => [
                'show' => true,
                'required' => true
            ],
            'zip' => [
                'show' => true,
                'required' => true
            ],
            'city' => [
                'show' => true,
                'required' => true
            ],
            'country' => [
                'show' => true,
                'required' => true
            ],
            'company' => [
                'show' => true,
                'required' => false
            ],
            'phone' => [
                'show' => true,
                'required' => false
            ],
            'mobile' => [
                'show' => true,
                'required' => false
            ],
            'fax' => [
                'show' => true,
                'required' => false
            ],
        ];

        $Conf->setValue('registration', 'addressFields', json_encode($addressFields));
        $Conf->setValue('profile', 'addressFields', json_encode($addressFields));
        $Conf->save();
    }

    /**
     * quiqqer/core: onTemplateGetHeader
     *
     * @param QUI\Template $TemplateManager
     */
    public static function onTemplateGetHeader(QUI\Template $TemplateManager): void
    {
        $cssFile = URL_OPT_DIR . 'quiqqer/frontend-users/bin/style.css';
        $TemplateManager->extendHeader('<link rel="stylesheet" type="text/css" href="' . $cssFile . '">');

        $User = QUI::getUserBySession();

        echo "<script>
            (function() {
                const registerNewLogin = function() {
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
                
                const waitForRequireEventRegister = setInterval(function() {
                    if (typeof require === 'undefined') {
                        return;
                    }
                    
                    clearInterval(waitForRequireEventRegister);
                    
                    let loadQUI = function () {
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
                const openChangePasswordWindow = function() {
                    require([
                        'controls/users/password/Window',
                        'Locale'
                    ], function(Password, QUILocale) {
                        new Password({
                            uid: '" . $User->getUUID() . "',
                            mustChange: true,
                            message: QUILocale.get('quiqqer/core', 'message.set.new.password'),
                            events: {
                                onSuccess: function() {
                                    window.location.reload();
                                }
                            }
                        }).open();
                    });
                };
           
                const checkChangePasswordWindow = function() {
                    require(['Locale'], function(QUILocale) {
                        if (!QUILocale.exists('quiqqer/core', 'message.set.new.password')) {
                            (function() {
                                openChangePasswordWindow();
                            }).delay(2000);
                            return;
                        }
                        
                        openChangePasswordWindow();
                    });            
                };
                
                let waitForRequire = setInterval(function() {
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
    protected static function createProfileCategoryViewPermissions(): void
    {
        $Permissions = new QUI\Permissions\Manager();
        $permissionPrefix = 'quiqqer.frontendUsers.profile.view.';
        $defaultViewPermission = (int)QUI::getPackage('quiqqer/frontend-users')->getConfig()->get(
            'user_profile',
            'categoryViewDefaultPermission'
        );

        // TODO $defaultViewPermission muss raus
        // TODO es wäre besser wenn das permission in der settings.xml gesetzt wird
        // die kategorien wissen eigentlich nur selbst
        // also so:
        /*    <category name="userProfile">
                    <permission name="view" type="bool">
                        <defaultvalue>0</defaultvalue>
                        <rootPermission>1</rootPermission>
                        <everyonePermission>0</everyonePermission>
                    </permission>
        */

        foreach (Utils::getProfileCategories() as $c) {
            foreach ($c['items'] as $setting) {
                $permission = $permissionPrefix . $c['name'] . '.' . $setting['name'];

                try {
                    $Permissions->getPermissionData($permission);
                    continue;
                } catch (\Exception) {
                    // if permission does not exist -> create it
                }

                $title = $permission;

                if (!empty($setting['title'])) {
                    if (is_string($setting['title'])) {
                        $title = $setting['title'];
                    } elseif (is_array($setting['title']) && count($setting['title']) === 2) {
                        $title = $setting['title'][0] . ' ' . $setting['title'][1];
                    }
                }

                $Permissions->addPermission([
                    'name' => $permission,
                    'title' => $title,
                    'desc' => '',
                    'type' => 'bool',
                    'area' => '',
                    'src' => 'quiqqer/frontend-users',
                    'defaultvalue' => $defaultViewPermission
                ]);
            }
        }
    }

    /**
     * Create the user upload folder
     * -> for avatars
     *
     * @throws QUI\Exception
     */
    public static function checkUserMediaFolder(): void
    {
        $Config = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $folder = $Config->getValue('userProfile', 'userAvatarFolder');

        try {
            QUI\Projects\Media\Utils::getMediaItemByUrl($folder);
        } catch (QUI\Exception) {
            $Standard = QUI::getProjectManager()->getStandard();
            $Media = $Standard->getMedia();
            $MainFolder = $Media->firstChild();

            try {
                $Folder = $MainFolder->getChildByName('user');
            } catch (QUI\Exception) {
                $Folder = $MainFolder->createFolder('user');
                $Folder->setHidden();
                $Folder->save();
            }

            $Config->setValue('userProfile', 'userAvatarFolder', $Folder->getUrl());
            $Config->save();
        }
    }

    public static function onTemplateEnd(
        Collector $Collection,
        QUI\Template $Template
    ): void {
        $Collection->append(
            '<script src="' . URL_OPT_DIR . 'quiqqer/frontend-users/bin/dataLayerTracking.js"></script>'
        );
    }
}
