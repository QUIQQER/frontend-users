<?php

/**
 * This file contains QUI\FrontendUsers\Controls\UserAvatar
 */

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\FrontendUsers\Handler;
use QUI\Projects\Media\Utils as QUIMediaUtils;
use QUI\Projects\Media\ExternalImage;
use QUI\FrontendUsers\Utils;

/**
 * Class UserAvatar
 *
 * @package QUI\FrontendUsers\Controls
 */
class UserAvatar extends AbstractProfileControl
{
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->setAttribute('data-qui', 'package/quiqqer/frontend-users/bin/frontend/controls/profile/UserAvatar');

        $this->addCSSClass('quiqqer-frontendUsers-UserAvatar');
        $this->addCSSFile(dirname(__FILE__) . '/UserAvatar.css');
    }

    /**
     * Return the control body
     *
     * @return string
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $settings         = Handler::getInstance()->getUserProfileSettings();
        $User             = QUI::getUserBySession();
        $userGravatarIcon = $User->getAttribute('quiqqer.frontendUsers.useGravatarIcon');
        $userEmail        = $User->getAttribute('email');
        $gravatarEnabled  = boolval($settings['useGravatar']);
        $AvatarImage      = false;
        $Engine           = QUI::getTemplateManager()->getEngine();

        if (!empty($userGravatarIcon) && $gravatarEnabled && !empty($userEmail)) {
            $userGravatarIcon = true;
            $AvatarImage      = new ExternalImage(Utils::getGravatarUrl($userEmail, 100));

            $Engine->assign(array(
                'avatarImageUrl' => $AvatarImage->getSizeCacheUrl(100, 100)
            ));
        } else {
            $avatarMediaUrl = $User->getAttribute('avatar');

            // if empty, us first Letter of the username
            if (!empty($avatarMediaUrl)) {
                try {
                    $AvatarImage = QUIMediaUtils::getImageByUrl($avatarMediaUrl);

                    $Engine->assign(array(
                        'avatarImageUrl' => $AvatarImage->getSizeCacheUrl(100, 100)
                    ));
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        $Engine->assign(array(
            'AvatarImage'      => $AvatarImage,
            'User'             => $User,
            'userGravatarIcon' => $userGravatarIcon,
            'gravatarEnabled'  => $gravatarEnabled
        ));

        $Engine->assign(
            'ProfileSite',
            QUI\FrontendUsers\Handler::getInstance()->getProfileSite(
                QUI::getRewrite()->getProject()
            )
        );

        return $Engine->fetch(dirname(__FILE__) . '/UserAvatar.html');
    }

    /**
     * event: on save
     *
     * @throws QUI\Exception
     */
    public function onSave()
    {
        $Request         = QUI::getRequest()->request;
        $settings        = Handler::getInstance()->getUserProfileSettings();
        $gravatarEnabled = boolval($settings['useGravatar']);
        $User            = QUI::getUserBySession();
        $useGravatar     = boolval($Request->get('useGravatar'));

        if ($gravatarEnabled) {
            $User->setAttribute('quiqqer.frontendUsers.useGravatarIcon', $useGravatar);
            $User->save();
        }
    }
}
