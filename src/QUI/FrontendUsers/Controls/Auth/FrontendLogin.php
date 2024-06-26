<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Auth\FrontendLogin
 */

namespace QUI\FrontendUsers\Controls\Auth;

use QUI;
use QUI\Exception;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\Handler;
use QUI\Users\Controls\Login;

/**
 * Class FrontendLogin
 *
 * @package QUI\FrontendUsers\Registrars
 */
class FrontendLogin extends QUI\Control
{
    /**
     * Control constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'showRegistration' => true
        ]);

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/FrontendLogin.css');
        $this->addCSSClass('quiqqer-frontendUsers-frontendlogin');
        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Handler = Handler::getInstance();
        $settings = $Handler->getLoginSettings();
        $redirectOnLogin = $settings['redirectOnLogin'];
        $projectLang = QUI::getRewrite()->getProject()->getLang();

        $dataRedirect = false;

        if (!empty($redirectOnLogin[$projectLang])) {
            $dataRedirect = 'data-redirect="' . $redirectOnLogin[$projectLang] . '"';
            $dataRedirect = QUI\Output::getInstance()->parse($dataRedirect);
        }

        $Registration = false;

        if ($this->getAttribute('showRegistration')) {
            $Registration = new Registration();
        }

        $Engine->assign([
            'Login' => new Login(),
            'Registration' => $Registration,
            'dataRedirect' => $dataRedirect
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/FrontendLogin.html');
    }
}
