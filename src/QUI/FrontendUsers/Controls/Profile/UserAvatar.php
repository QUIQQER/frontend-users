<?php

/**
 * This file contains QUI\FrontendUsers\Controls\UserAvatar
 */

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Utils;
use QUI\Projects\Media\ExternalImage;
use QUI\Projects\Media\Utils as QUIMediaUtils;

use function boolval;
use function dirname;

/**
 * Class UserAvatar
 *
 * @package QUI\FrontendUsers\Controls
 */
class UserAvatar extends AbstractProfileControl
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttribute('data-qui', 'package/quiqqer/frontend-users/bin/frontend/controls/profile/UserAvatar');

        $this->addCSSClass('quiqqer-frontendUsers-UserAvatar');

        if (!defined('QUIQQER_CONTROL_TEMPLATE_USE_BASIC') || QUIQQER_CONTROL_TEMPLATE_USE_BASIC !== true) {
            $this->addCSSFile(dirname(__FILE__) . '/UserAvatar.css');
        }
    }

    /**
     * Return the control body
     *
     * @return string
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $settings = Handler::getInstance()->getUserProfileSettings();
        $User = QUI::getUserBySession();
        $userGravatarIcon = $User->getAttribute('quiqqer.frontendUsers.useGravatarIcon');
        $userEmail = $User->getAttribute('email');
        $gravatarEnabled = boolval($settings['useGravatar']);
        $AvatarImage = false;
        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign([
            'avatarImageUrl' => ''
        ]);

        if (!empty($userGravatarIcon) && $gravatarEnabled && !empty($userEmail)) {
            $userGravatarIcon = true;
            $AvatarImage = new ExternalImage(Utils::getGravatarUrl($userEmail, 100));
            $url = $AvatarImage->getSizeCacheUrl(100, 100);

            $Engine->assign([
                'avatarImageUrl' => ' style="background-image: url(\'' . $url . '\')"'
            ]);
        } else {
            $avatarMediaUrl = $User->getAttribute('avatar');

            // if empty, us first Letter of the username
            if (!empty($avatarMediaUrl)) {
                try {
                    $AvatarImage = QUIMediaUtils::getImageByUrl($avatarMediaUrl);

                    $Engine->assign([
                        'avatarImageUrl' => ' style="background-image: url(\'' .
                            $AvatarImage->getSizeCacheUrl(100, 100) . '\')"'
                    ]);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        $uploadEnabled = boolval(
            QUI::getPackage('quiqqer/frontend-users')
                ->getConfig()
                ->get('userProfile', 'userAvatarUploadAllowed')
        );

        if ($uploadEnabled) {
            $Engine->assign('AvatarUpload', new UserAvatarUpload());
        }

        $Engine->assign([
            'AvatarImage' => $AvatarImage,
            'User' => $User,
            'userGravatarIcon' => $userGravatarIcon,
            'gravatarEnabled' => $gravatarEnabled,
            'uploadEnabled' => $uploadEnabled
        ]);

        $Engine->assign(
            'ProfileSite',
            QUI\FrontendUsers\Handler::getInstance()->getProfileSite(
                QUI::getRewrite()->getProject()
            )
        );

        return $Engine->fetch($this->getTemplateFile());
    }

    /**
     * event: on save
     *
     * @throws QUI\Exception
     */
    public function onSave(): void
    {
        $Request = QUI::getRequest()->request;
        $settings = Handler::getInstance()->getUserProfileSettings();
        $gravatarEnabled = boolval($settings['useGravatar']);
        $User = QUI::getUserBySession();
        $useGravatar = boolval($Request->get('useGravatar'));

        if ($gravatarEnabled) {
            $User->setAttribute('quiqqer.frontendUsers.useGravatarIcon', $useGravatar);
            $User->save();
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'message.user.saved.successfully'
            )
        );
    }
}
