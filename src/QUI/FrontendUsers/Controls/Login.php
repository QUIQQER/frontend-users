<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Login
 */

namespace QUI\FrontendUsers\Controls;

use QUI;

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
            'registrars' => [],
            'Registrar'  => false // currently executed Registrar
        ]);

        $this->setAttributes($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Login.css');
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
    public function getBody()
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            return '';
        }

        $authenticators = QUI\Users\Auth\Handler::getInstance()->getAvailableAuthenticators();
        $instances      = [];

        $socialAuth = array_filter($authenticators, function ($authenticator) {
            return $authenticator !== QUI\Users\Auth\QUIQQER::class;
        });

        foreach ($socialAuth as $class) {
            try {
                /* @var $Auth QUI\Users\AbstractAuthenticator */
                $Auth = new $class([
                    'onlyIcon' => true
                ]);

                $Login = $Auth->getLoginControl();

                if (!$Login) {
                    continue;
                }

                $instances[] = [
                    'Auth'  => $Auth,
                    'Login' => $Login,
                    'class' => $class
                ];
            } catch (\Exception $Exception) {
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

        $Engine->assign([
            'this'           => $this,
            'authenticators' => $instances,
            'SessionUser'    => QUI::getUserBySession(),

            'showPasswordReset' => $showPasswordReset
        ]);

        return $Engine->fetch(dirname(__FILE__).'/Login.html');
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
}
