<?php

/**
 * This file contains QUI\FrontendUsers\Controls\ProfileBar
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\Control;
use QUI\FrontendUsers\Handler;

/**
 * Class ProfileBar
 *
 * @package QUI\FrontendUsers\Controls
 */
class ProfileBar extends Control
{
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->setAttribute('data-qui', 'package/quiqqer/frontend-users/bin/frontend/controls/ProfileBar');

        $this->addCSSClass('quiqqer-frontendUsers-profileBar');
        $this->addCSSFile(dirname(__FILE__) . '/ProfileBar.css');
    }

    /**
     * Return the control body
     *
     * @return string
     */
    public function getBody()
    {
        $Engine   = QUI::getTemplateManager()->getEngine();
        $User     = QUI::getUserBySession();
        $Handler  = Handler::getInstance();
        $Project  = QUI::getRewrite()->getProject();
        $settings = $Handler->getProfileBarSettings();

        $Engine->assign(array(
            'isAuth'           => boolval($User->getId()),
            'UserIcon'         => new UserIcon(array(
                'User' => $User
            )),
            'showLogin'        => boolval($settings['showLogin']),
            'showRegistration' => boolval($settings['showRegistration']),
            'LoginSite'        => $Handler->getLoginSite($Project),
            'RegistrationSite' => $Handler->getRegistrationSite($Project)
        ));

        return $Engine->fetch(dirname(__FILE__) . '/ProfileBar.html');
    }
}
