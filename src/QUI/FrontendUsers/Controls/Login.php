<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Login
 */

namespace QUI\FrontendUsers\Controls;

use Exception;
use QUI;

use function base64_decode;

/**
 * Class Login
 *
 * This login is an extended version of the normal login
 * This login includes social logins
 */
class Login extends QUI\Control
{
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
            'authenticators' => [],
            'Authenticator' => false,  // currently executed Registrar
            'mail' => true,   // show mail authenticator
            'passwordReset' => true,   // show password reset
            'header' => true    // show header title
        ]);

        $this->setAttributes($attributes);

        if (!defined('QUIQQER_CONTROL_TEMPLATE_USE_BASIC') || QUIQQER_CONTROL_TEMPLATE_USE_BASIC !== true) {
            $this->addCSSFile(dirname(__FILE__) . '/Login.css');
        }

        $this->addCSSClass('quiqqer-fu-login');

        $this->setJavaScriptControl(
            'package/quiqqer/frontend-users/bin/frontend/controls/login/Login'
        );
    }

    /**
     * Return the control body
     *
     * @return string
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $authenticators = $this->getAuthenticators();
        $instances = [];

        $socialAuth = array_filter($authenticators, function ($authenticator) {
            return $authenticator !== QUI\Users\Auth\QUIQQER::class;
        });

        foreach ($socialAuth as $class) {
            try {
                /* @var $Auth QUI\Users\AbstractAuthenticator */
                $Auth = new $class();

                $Login = $Auth->getLoginControl();
                $Login->setAttributes([
                    'onlyIcon' => true
                ]);

                $icon = false;
                $image = false;
                $iconAttribute = $Login->getAttribute('icon');

                if (
                    str_contains($iconAttribute, 'fa ')
                    || str_contains($iconAttribute, 'fab ')
                    || str_contains($iconAttribute, 'fas ')
                ) {
                    $icon = true;
                } elseif ($iconAttribute !== '') {
                    $image = true;
                }

                $instances[] = [
                    'Auth' => $Auth,
                    'Login' => $Login,
                    'class' => $class,
                    'icon' => $icon,
                    'image' => $image
                ];
            } catch (Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        // show password reset - yes / no
        $showPasswordReset = false;

        if (QUI\Users\Auth\Handler::getInstance()->isQuiqqerVerificationPackageInstalled()) {
            if (!empty($_REQUEST['isAdminLogin']) || QUI::isBackend()) {
                $showPasswordReset = boolval(QUI::conf('auth_settings', 'showResetPasswordBackend'));
            } else {
                $showPasswordReset = boolval(QUI::conf('auth_settings', 'showResetPasswordFrontend'));
            }
        }

        if (!$this->getAttribute('passwordReset')) {
            $showPasswordReset = false;
        }

        $Engine->assign([
            'this' => $this,
            'authenticators' => $instances,
            'SessionUser' => QUI::getUserBySession(),
            'showPasswordReset' => $showPasswordReset
        ]);

        return $Engine->fetch($this->getTemplateFile());
    }

    /**
     * Get all Authenticators that are displayed
     *
     * @return string[] - Authenticator class paths
     */
    protected function getAuthenticators(): array
    {
        $authenticators = QUI\Users\Auth\Handler::getInstance()->getAvailableAuthenticators();
        $filterRegistrars = $this->getAttribute('authenticators');

        // Parse allowed authenticators
        try {
            $loginSettings = QUI\FrontendUsers\Handler::getInstance()->getLoginSettings();
            $authenticatorSettings = $loginSettings['authenticators'];
            $allowed = [
                'QUI\Users\Auth\QUIQQER'
            ];

            foreach ($authenticatorSettings as $authenticatorHash => $active) {
                if ($active) {
                    $allowed[] = base64_decode($authenticatorHash);
                }
            }

            $authenticators = array_filter($authenticators, function ($authenticator) use ($allowed) {
                return in_array($authenticator, $allowed);
            });
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        if (empty($filterRegistrars)) {
            return $authenticators;
        }

        return array_filter($authenticators, function ($authenticator) use ($filterRegistrars) {
            return in_array($authenticator, $filterRegistrars);
        });
    }
}
