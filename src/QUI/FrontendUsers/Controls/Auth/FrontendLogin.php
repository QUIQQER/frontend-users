<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Auth\FrontendLogin
 */

namespace QUI\FrontendUsers\Controls\Auth;

use QUI;
use QUI\Users\Controls\Login;
use QUI\FrontendUsers\Handler;

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
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/FrontendLogin.css');
        $this->addCSSClass('quiqqer-frontendUsers-frontendlogin');
        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine          = QUI::getTemplateManager()->getEngine();
        $Handler         = Handler::getInstance();
        $settings        = $Handler->getLoginSettings();
        $redirectOnLogin = $settings['redirectOnLogin'];

        $dataRedirect = false;

        if ($redirectOnLogin) {
            $dataRedirect = 'data-redirect="' . $redirectOnLogin . '"';
            $dataRedirect = QUI\Output::getInstance()->parse($dataRedirect);
        }

        $Engine->assign(array(
            'Login'        => new Login(),
            'dataRedirect' => $dataRedirect
        ));

        return $Engine->fetch(dirname(__FILE__) . '/FrontendLogin.html');
    }
}
