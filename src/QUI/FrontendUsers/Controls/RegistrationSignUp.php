<?php

/**
 * This file contains QUI\FrontendUsers\Controls\RegistrationSignUp
 */

namespace QUI\FrontendUsers\Controls;

use QUI;

/**
 * Class RegistrationSignUp
 *
 * @package QUI\FrontendUsers\Controls
 */
class RegistrationSignUp extends QUI\Control
{
    /**
     * Registration ID (for this runtime only)
     *
     * @var string
     */
    protected $id;

    /**
     * constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttributes([
            // if empty load all default Registrars, otherwise load the ones provided here
            'registrars' => [],

            'Registrar'          => false, // currently executed Registrar
            'content'            => '',    // right content
            'registration-trial' => false  // use registration trial
        ]);

        $this->setAttributes($attributes);

        $this->id = QUI\FrontendUsers\Handler::getInstance()->createRegistrationId();

        $this->addCSSFile(dirname(__FILE__).'/RegistrationSignUp.css');
        $this->addCSSClass('quiqqer-fu-registrationSignUp');

        $this->setJavaScriptControl(
            'package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignUp'
        );
    }

    /**
     * Return the control body
     *
     * @return string
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $this->siteTermsPrivacy($Engine);

        $Registrars        = $this->getRegistrars();
        $RegistrationTrial = null;

        $RegistrarHandler     = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();

        // get email registrar
        $email = $Registrars->filter(function ($Registrar) {
            return $Registrar instanceof QUI\FrontendUsers\Registrars\Email\Registrar;
        });

        // trial registration
        if ($this->getAttribute('registration-trial')) {
            $registrationTrial = $Registrars->filter(function ($Registrar) {
                return $Registrar instanceof QUI\Registration\Trial\Registrar;
            });

            if (isset($registrationTrial[0])) {
                $RegistrationTrial = $registrationTrial[0];
            }
        }

        // captcha usage
        $Captcha            = false;
        $jsRequired         = false;
        $useCaptcha         = false;
        $isCaptchaInvisible = false;

        if (QUI\FrontendUsers\Utils::isCaptchaModuleInstalled()) {
            $Captcha    = new QUI\Captcha\Controls\CaptchaDisplay();
            $jsRequired = QUI\Captcha\Handler::requiresJavaScript();
            $useCaptcha = boolval($registrationSettings['useCaptcha']);

            $Default            = QUI\Captcha\Handler::getDefaultCaptchaModuleControl();
            $isCaptchaInvisible = QUI\Captcha\Handler::isInvisible();

            if (class_exists('QUI\Captcha\Modules\Google')
                && $Default->getType() === QUI\Captcha\Modules\GoogleInvisible\Control::class) {
                $Engine->assign('googleSideKey', QUI\Captcha\Modules\Google::getSiteKey());
            }
        }

        $this->setJavaScriptControlOption('usecaptcha', $useCaptcha);

        $Engine->assign([
            'Captcha'            => $Captcha,
            'useCaptcha'         => $useCaptcha,
            'jsRequired'         => $jsRequired,
            'isCaptchaInvisible' => $isCaptchaInvisible
        ]);

        $Engine->assign([
            'captchaHTML' => $Engine->fetch(dirname(__FILE__).'/RegistrationSignUp.Captcha.html')
        ]);

        // default stuff
        $Registrars = $Registrars->filter(function ($Registrar) {
            $class    = get_class($Registrar);
            $haystack = [
                QUI\FrontendUsers\Registrars\Email\Registrar::class
            ];

            if (QUI::getPackageManager()->isInstalled('quiqqer/registration-trial')) {
                $haystack[] = QUI\Registration\Trial\Registrar::class;
            }

            $haystack = array_flip($haystack);

            return !isset($haystack[$class]);
        });

        // Sort registrars by display position
        $Registrars->sort(function ($RegistrarA, $RegistrarB) use ($RegistrarHandler) {
            $settingsA        = $RegistrarHandler->getRegistrarSettings(get_class($RegistrarA));
            $settingsB        = $RegistrarHandler->getRegistrarSettings(get_class($RegistrarB));
            $displayPositionA = (int)$settingsA['displayPosition'];
            $displayPositionB = (int)$settingsB['displayPosition'];

            return $displayPositionA - $displayPositionB;
        });


        // show email registrar
        $Email = false;

        if (isset($email[0])) {
            $Email = $email[0];
        }

        // messages
        $isLoggedIn          = QUI::getUsers()->isAuth(QUI::getUserBySession());
        $showLoggedInWarning = $isLoggedIn;
        $msgSuccess          = false;
        $msgError            = false;
        $activationSuccess   = false;

        if (!empty($_GET['success'])) {
            switch ($_GET['success']) {
                case 'activation':
                    if ($isLoggedIn) {
                        $msgSuccess = QUI::getLocale()->get(
                            'quiqqer/frontend-users',
                            'RegistrationSignUp.message.success.activation_logged_in'
                        );
                    } else {
                        $msgSuccess = QUI::getLocale()->get(
                            'quiqqer/frontend-users',
                            'RegistrationSignUp.message.success.activation'
                        );
                    }

                    $activationSuccess   = true;
                    $showLoggedInWarning = false;
                    break;
                case 'emailconfirm':
                case 'userdelete':
                    $msgSuccess = QUI::getLocale()->get(
                        'quiqqer/frontend-users',
                        'RegistrationSignUp.message.success.'.$_GET['success']
                    );

                    $showLoggedInWarning = false;
                    break;
            }
        }

        if (!empty($_GET['error'])) {
            switch ($_GET['error']) {
                case 'activation':
                case 'emailconfirm':
                case 'userdelete':
                    $msgError = QUI::getLocale()->get(
                        'quiqqer/frontend-users',
                        'RegistrationSignUp.message.error.'.$_GET['error']
                    );

                    $showLoggedInWarning = false;
                    break;
            }
        }

        // Auto-redirect
        $redirect             = false;
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();
        $projectLang          = $Project = QUI::getRewrite()->getProject()->getLang();

        if ($activationSuccess && !empty($registrationSettings['autoRedirectOnSuccess'][$projectLang])) {
            $RedirectSite = QUI\Projects\Site\Utils::getSiteByLink(
                $registrationSettings['autoRedirectOnSuccess'][$projectLang]
            );

            $redirect = $RedirectSite->getUrlRewrittenWithHost();
        }

        $Engine->assign([
            'this'                => $this,
            'Registrars'          => $Registrars,
            'Email'               => $Email,
            'registrationId'      => $this->id,
            'RegistrationTrial'   => $RegistrationTrial,
            'showLoggedInWarning' => $showLoggedInWarning,
            'msgSuccess'          => $msgSuccess,
            'msgError'            => $msgError,
            'redirect'            => $redirect
        ]);

        return $Engine->fetch(dirname(__FILE__).'/RegistrationSignUp.html');
    }

    /**
     * Get all Registrars that are displayed
     *
     * @return QUI\FrontendUsers\RegistrarCollection
     */
    protected function getRegistrars()
    {
        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
        $filterRegistrars = $this->getAttribute('registrars');
        $Registrars       = $RegistrarHandler->getRegistrars();

        if (empty($filterRegistrars)) {
            return $Registrars;
        }

        $registrars         = $Registrars->toArray();
        $FilteredRegistrars = new QUI\FrontendUsers\RegistrarCollection();

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
     * generate the data fpr site terms of use and privacy policy
     *
     * @param QUI\Interfaces\Template\EngineInterface $Engine
     * @throws QUI\Exception
     */
    protected function siteTermsPrivacy($Engine)
    {
        $Project = $this->getProject();

        // privacy and terms of use message
        /* @var $SiteTerms QUI\Projects\Site */
        /* @var $SitePrivacy QUI\Projects\Site */
        $SiteTerms   = null;
        $SitePrivacy = null;

        // AGB
        $result = $Project->getSites([
            'where' => [
                'type' => 'quiqqer/sitetypes:types/generalTermsAndConditions'
            ],
            'limit' => 1
        ]);

        if (isset($result[0])) {
            $SiteTerms = $result[0];
        }

        // Privacy
        $result = $Project->getSites([
            'where' => [
                'type' => 'quiqqer/sitetypes:types/privacypolicy'
            ],
            'limit' => 1
        ]);

        if (isset($result[0])) {
            $SitePrivacy = $result[0];
        }

        $termsPrivacyMessage = QUI::getLocale()->get(
            'quiqqer/frontend-users',
            'control.registration.terms_of_use.info'
        );

        if ($SiteTerms && $SitePrivacy) {
            $termsPrivacyMessage = QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'control.sign.up.terms_of_use_and_privacy_policy.label',
                [
                    'termsOfUseUrl'          => $SiteTerms->getUrlRewritten(),
                    'termsOfUseSiteTitle'    => $SiteTerms->getAttribute('title'),
                    'privacyPolicyUrl'       => $SitePrivacy->getUrlRewritten(),
                    'privacyPolicySiteTitle' => $SitePrivacy->getAttribute('title')
                ]
            );
        } elseif ($SiteTerms) {
            $termsPrivacyMessage = QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'control.sign.up.terms_of_use.label',
                [
                    'termsOfUseUrl'       => $SiteTerms->getUrlRewritten(),
                    'termsOfUseSiteTitle' => $SiteTerms->getAttribute('title')
                ]
            );
        } elseif ($SitePrivacy) {
            $termsPrivacyMessage = QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'control.sign.up.privacy_policy.label',
                [
                    'privacyPolicyUrl'       => $SitePrivacy->getUrlRewritten(),
                    'privacyPolicySiteTitle' => $SitePrivacy->getAttribute('title')
                ]
            );
        }

        $Engine->assign('termsPrivacyMessage', $termsPrivacyMessage);
    }
}
