<?php

/**
 * This file contains QUI\FrontendUsers\Controls\UserIcon
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\Control;
use QUI\FrontendUsers\Handler;
use QUI\Projects\Media\Utils as QUIMediaUtils;
use QUI\Projects\Media\ExternalImage;
use QUI\FrontendUsers\Utils;

/**
 * Class UserIcon
 *
 * @package QUI\FrontendUsers\Controls
 */
class UserIcon extends Control
{
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'iconWidth'  => 50,
            'iconHeight' => 50,
            'showLogout' => true
        ]);

        parent::__construct($attributes);

        $this->setAttribute('data-qui', 'package/quiqqer/frontend-users/bin/frontend/controls/UserIcon');

        $this->addCSSClass('quiqqer-frontendUsers-userIcon');
        $this->addCSSFile(dirname(__FILE__).'/UserIcon.css');
    }

    /**
     * Return the control body
     *
     * @return string
     * @throws QUI\Exception
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

        $this->setAttribute('data-qui-options-showlogout', $this->getAttribute('showLogout'));

        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign([
            'User' => $User
        ]);

        $settings         = Handler::getInstance()->getUserProfileSettings();
        $User             = QUI::getUserBySession();
        $userGravatarIcon = $User->getAttribute('quiqqer.frontendUsers.useGravatarIcon');
        $userEmail        = $User->getAttribute('email');
        $gravatarEnabled  = boolval($settings['useGravatar']);
        $iconWidth        = (int)$this->getAttribute('iconWidth');
        $iconHeight       = (int)$this->getAttribute('iconHeight');

        if (!empty($userGravatarIcon) && $gravatarEnabled && !empty($userEmail)) {
            $AvatarImage = new ExternalImage(Utils::getGravatarUrl($userEmail, $iconHeight));

            $Engine->assign([
                'avatarImageUrl' => $AvatarImage->getSizeCacheUrl($iconWidth, $iconHeight)
            ]);
        } else {
            $avatarMediaUrl = $User->getAttribute('avatar');
            $AvatarImage    = false;

            // if empty, us first Letter of the username
            if (!empty($avatarMediaUrl)) {
                try {
                    $AvatarImage = QUIMediaUtils::getImageByUrl($avatarMediaUrl);

                    $Engine->assign([
                        'avatarImageUrl' => $AvatarImage->getSizeCacheUrl($iconWidth, $iconHeight)
                    ]);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            } else {
                $AvatarImage = $this->getDefaultAvatarImage();

                if (\is_string($AvatarImage)) {
                    $Engine->assign('avatarImageIcon', $AvatarImage);
                } elseif ($AvatarImage !== false) {
                    $Engine->assign([
                        'avatarImageUrl' => $AvatarImage->getSizeCacheUrl($iconWidth, $iconHeight)
                    ]);
                }
            }

            if ($AvatarImage === false) {
                $username    = $User->getUsername();
                $firstLetter = mb_substr($username, 0, 1);
                $firstLetter = mb_strtoupper($firstLetter);
                $Engine->assign('firstLetter', $firstLetter);
            }
        }

        $Engine->assign([
            'User'       => $User,
            'iconHeight' => $iconHeight,
            'iconWidth'  => $iconWidth
        ]);

        $Engine->assign(
            'ProfileSite',
            QUI\FrontendUsers\Handler::getInstance()->getProfileSite(
                QUI::getRewrite()->getProject()
            )
        );

        return $Engine->fetch(dirname(__FILE__).'/UserIcon.html');
    }

    /**
     * Get default avatar image
     *
     * @return false|QUI\Projects\Media\Image|string
     */
    protected function getDefaultAvatarImage()
    {
        try {
            $Conf               = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $defaultAvatarImage = $Conf->get('profileBar', 'defaultAvatar');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        if (empty($defaultAvatarImage)) {
            return false;
        }

        // Check if icon
        if (\mb_strpos($defaultAvatarImage, 'image.php') === false) {
            return $defaultAvatarImage;
        }

        try {
            return QUIMediaUtils::getImageByUrl($defaultAvatarImage);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }
    }
}
