<?php

/**
 * This file contains QUI\FrontendUsers\Controls\ProfileBar
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\Control;
use QUI\Exception;
use QUI\FrontendUsers\Handler;

/**
 * Class ProfileBar
 *
 * @package QUI\FrontendUsers\Controls
 */
class ProfileBar extends Control
{
    /**
     * ProfileBar constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttribute(
            'data-qui',
            'package/quiqqer/frontend-users/bin/frontend/controls/ProfileBar'
        );

        $this->addCSSClass('quiqqer-frontendUsers-profileBar');
        $this->addCSSFile(dirname(__FILE__) . '/ProfileBar.css');
    }

    /**
     * Return the control body
     *
     * @return string
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User = QUI::getUserBySession();
        $Project = QUI::getRewrite()->getProject();

        $Handler = Handler::getInstance();
        $settings = $Handler->getProfileBarSettings();

        $Engine->assign([
            'isAuth' => boolval($User->getId()),
            'UserIcon' => new UserIcon([
                'User' => $User
            ]),
            'showLogin' => boolval($settings['showLogin']),
            'showRegistration' => boolval($settings['showRegistration']),
            'LoginSite' => $Handler->getLoginSite($Project),
            'RegistrationSite' => $Handler->getRegistrationSite($Project)
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/ProfileBar.html');
    }
}
