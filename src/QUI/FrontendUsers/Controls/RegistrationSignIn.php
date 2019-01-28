<?php

/**
 * This file contains QUI\FrontendUsers\Controls\RegistrationSignIn
 */

namespace QUI\FrontendUsers\Controls;

use QUI;

/**
 * Class RegistrationSignIn
 *
 * @package QUI\FrontendUsers\Controls
 */
class RegistrationSignIn extends QUI\Control
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

        $this->addCSSFile(dirname(__FILE__).'/RegistrationSignIn.css');
        $this->addCSSClass('quiqqer-fu-registrationSignIn');

        $this->setJavaScriptControl(
            'package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignIn'
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
        $RegistrarHandler  = QUI\FrontendUsers\Handler::getInstance();
        $RegistrationTrial = null;

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


        $Engine->assign([
            'this'              => $this,
            'Registrars'        => $Registrars,
            'Email'             => $Email,
            'registrationId'    => $this->id,
            'RegistrationTrial' => $RegistrationTrial
        ]);

        return $Engine->fetch(dirname(__FILE__).'/RegistrationSignIn.html');
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
                'control.registration.terms_of_use_and_privacy_policy.label',
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
                'control.registration.terms_of_use.label',
                [
                    'termsOfUseUrl'       => $SiteTerms->getUrlRewritten(),
                    'termsOfUseSiteTitle' => $SiteTerms->getAttribute('title')
                ]
            );
        } elseif ($SitePrivacy) {
            $termsPrivacyMessage = QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'control.registration.privacy_policy.label',
                [
                    'privacyPolicyUrl'       => $SitePrivacy->getUrlRewritten(),
                    'privacyPolicySiteTitle' => $SitePrivacy->getAttribute('title')
                ]
            );
        }

        $Engine->assign('termsPrivacyMessage', $termsPrivacyMessage);
    }
}
