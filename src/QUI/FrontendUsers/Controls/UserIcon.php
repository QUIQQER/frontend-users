<?php

/**
 * This file contains QUI\FrontendUsers\Controls\UserIcon
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\Control;

/**
 * Class UserIcon
 *
 * @package QUI\FrontendUsers\Controls
 */
class UserIcon extends Control
{
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->setAttribute('data-qui', 'package/quiqqer/frontend-users/bin/frontend/controls/UserIcon');

        $this->addCSSClass('quiqqer-frontendUsers-userIcon');
        $this->addCSSFile(dirname(__FILE__) . '/UserIcon.css');
    }

    /**
     * Return the control body
     *
     * @return string
     */
    public function getBody()
    {
        $User = $this->getAttribute('User');

        if ($User === false) {
            return '';
        }


        if (!($User instanceof QUI\Interfaces\Users\User)) {
            return '';
        }

        $Engine = QUI::getTemplateManager()->getEngine();
        $avatar = $User->getAttribute('avatar');

        $Engine->assign(array(
            'User' => $User
        ));

        // if empty, us first Letter of the username
        $Avatar = false;

        if (!empty($avatar)) {
            try {
                $Avatar = QUI\Projects\Media\Utils::getImageByUrl($avatar);

                $Engine->assign(array(
                    'Avatar' => $Avatar,
                    'avatar' => $Avatar->getSizeCacheUrl(100, 100)
                ));
            } catch (QUI\Exception $Exception) {
            }
        }

        if ($Avatar === false) {
            $username    = $User->getUsername();
            $firstLetter = mb_substr($username, 0, 1);
            $firstLetter = mb_strtoupper($firstLetter);

            $Engine->assign('firstLetter', $firstLetter);
        }

        $Engine->assign(
            'ProfileSite',
            QUI\FrontendUsers\Handler::getInstance()->getProfileSite(
                QUI::getRewrite()->getProject()
            )
        );

        return $Engine->fetch(dirname(__FILE__) . '/UserIcon.html');
    }
}
