<?php

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;

/**
 * Class UserAvatarUpload
 * @package QUI\FrontendUsers\Controls\Profile
 */
class UserAvatarUpload extends \QUI\Upload\Form
{
    /**
     * UserAvatarUpload constructor.
     *
     * @param array $params
     * @throws QUI\Exception
     */
    public function __construct($params = [])
    {
        parent::__construct($params);

        $Config = QUI::getPackage('quiqqer/frontend-users')->getConfig();

        $this->setAttributes([
            'contextMenu'       => false,
            'multiple'          => false,
            'sendbutton'        => false,
            'uploads'           => 1,
            'hasFile'           => false,
            'deleteFile'        => true,

            // eq: ['image/jpeg', 'image/png'] - nur nutzbar mit eigener Klasse
            'allowedFileTypes'  => ['image/*'],

            // eq: ['.gif', '.jpg']  - nur nutzbar mit eigener Klasse
            'allowedFileEnding' => ['*.gif', '*.jpg', '*.png', '*.jpeg'],

            'maxFileSize'    => $Config->getValue('userProfile', 'maxAvatarUploadSize'),
            'typeOfLook'     => 'Single',
            'typeOfLookIcon' => 'fa fa-upload'
        ]);
    }

    /**
     * @param $file
     * @param $params
     *
     * @throws QUI\Exception
     */
    public function onFileFinish($file, $params)
    {
        $Config = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $folder = $Config->getValue('userProfile', 'userAvatarFolder');

        $error       = ['quiqqer/frontend-users', 'exception.upload.avatar.error'];
        $SessionUser = QUI::getUserBySession();

        try {
            $UserFolder = QUI\Projects\Media\Utils::getMediaItemByUrl($folder);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError('Upload Error: '.$Exception->getMessage());

            throw new QUI\Exception($error);
        }

        if (!($UserFolder instanceof QUI\Projects\Media\Folder)) {
            throw new QUI\Exception($error);
        }

        $PermissionUser = QUI::getUsers()->getSystemUser();

        try {
            $Avatar = QUI\Projects\Media\Utils::getImageByUrl(
                $SessionUser->getAttribute('avatar')
            );

            if ($Avatar->getParent()->getId() === $UserFolder->getId()) {
                $Avatar->delete($PermissionUser);
            }
        } catch (QUI\Exception $Exception) {
        }

        $File = $UserFolder->uploadFile(
            $file,
            QUI\Projects\Media\Folder::FILE_OVERWRITE_NONE,
            $PermissionUser
        );

        $File->activate(QUI::getUsers()->getSystemUser());
        $File->rename($SessionUser->getUniqueId(), $PermissionUser);
        $File->setTitle($SessionUser->getUsername());

        $SessionUser->setAttribute('avatar', $File->getUrl());
        $SessionUser->save();
    }
}
