<?php

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\Projects\Media\Utils as MediaUtils;
use QUI\Utils\System\File as FileUtils;

use function rename;

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

        if ($this->getAttribute('User') instanceof QUI\Interfaces\Users\User) {
            $SessionUser = $this->getAttribute('User');
        }

        try {
            $UserFolder = QUI\Projects\Media\Utils::getMediaItemByUrl($folder);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError('Upload Error: ' . $Exception->getMessage());

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
        } catch (QUI\Exception) {
        }

        // rename file to user file
        $fileInfo = FileUtils::getInfo($file);
        $fileName = $fileInfo['dirname'] . '/' . $SessionUser->getUniqueId() . '.' . $fileInfo['extension'];

        rename($file, $fileName);

        $File = $UserFolder->uploadFile(
            $fileName,
            QUI\Projects\Media\Folder::FILE_OVERWRITE_TRUE,
            $PermissionUser
        );

        $File->activate(QUI::getUsers()->getSystemUser());
        $File->setTitle($SessionUser->getUsername());

        $SessionUser->setAttribute('avatar', $File->getUrl());
        $SessionUser->save();
    }
}
