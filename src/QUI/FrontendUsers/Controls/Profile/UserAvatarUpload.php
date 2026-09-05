<?php

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\Upload\Form;
use QUI\Utils\System\File as FileUtils;

use function rename;
use function strtolower;
use function trim;

/**
 * Class UserAvatarUpload
 * @package QUI\FrontendUsers\Controls\Profile
 */
class UserAvatarUpload extends Form
{
    private const CLEANUP_ATTRIBUTE = 'quiqqer.frontendUsers.avatarCleanup';

    /**
     * UserAvatarUpload constructor.
     *
     * @param array<string, mixed> $params
     * @throws QUI\Exception
     */
    public function __construct(array $params = [])
    {
        parent::__construct($params);

        $Config = QUI\FrontendUsers\Handler::getPackageConfig();

        $this->setAttributes([
            'contextMenu' => false,
            'multiple' => false,
            'sendbutton' => false,
            'uploads' => 1,
            'hasFile' => false,
            'deleteFile' => true,

            // eq: ['image/jpeg', 'image/png'] - nur nutzbar mit eigener Klasse
            'allowedFileTypes' => ['image/*'],

            // eq: ['.gif', '.jpg']  - nur nutzbar mit eigener Klasse
            'allowedFileEnding' => ['*.gif', '*.jpg', '*.png', '*.jpeg'],

            'maxFileSize' => $Config->getValue('userProfile', 'maxAvatarUploadSize'),
            'typeOfLook' => 'Single',
            'typeOfLookIcon' => 'fa fa-upload'
        ]);
    }

    /**
     * @param $file
     * @param $params
     *
     * @throws QUI\Exception
     */
    public function onFileFinish($file, $params): void
    {
        $Config = QUI\FrontendUsers\Handler::getPackageConfig();
        $folder = $Config->getValue('userProfile', 'userAvatarFolder');

        $error = ['quiqqer/frontend-users', 'exception.upload.avatar.error'];
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
        // Only this uploader creates this user-specific namespace. Legacy UUID names
        // do not prove ownership and must not be removed with system permissions.
        $prefix = 'frontend-users-avatar-' . hash('sha256', (string)$SessionUser->getUUID()) . '-';
        $pending = $SessionUser->getAttribute(self::CLEANUP_ATTRIBUTE);
        $previousUrls = is_array($pending) ? $pending : [];
        $previousUrls[] = $SessionUser->getAttribute('avatar');
        $previousAvatars = [];

        foreach ($previousUrls as $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }

            try {
                $Avatar = QUI\Projects\Media\Utils::getImageByUrl($url);

                if ($this->canRemoveAvatar($Avatar, $UserFolder, $prefix)) {
                    $previousAvatars[$Avatar->getUrl()] = $Avatar;
                }
            } catch (QUI\Exception) {
                // Already removed or no longer a resolvable local image.
            }
        }

        // rename file to user file
        $fileInfo = FileUtils::getInfo($file);

        if (empty($fileInfo['extension'])) {
            $fileInfo['extension'] = QUI\Utils\System\File::getEndingByMimeType($fileInfo['mime_type']);
            $fileInfo['extension'] = trim($fileInfo['extension'], '.');
        }

        $uuid = QUI\Utils\Uuid::get();
        $fileName = $fileInfo['dirname'] . '/' . $prefix . $uuid . '.' . strtolower($fileInfo['extension']);

        rename($file, $fileName);

        $File = $UserFolder->uploadFile(
            $fileName,
            QUI\Projects\Media\Folder::FILE_OVERWRITE_TRUE,
            $PermissionUser
        );

        $File->activate(QUI::getUsers()->getSystemUser());

        if (method_exists($File, 'setTitle')) {
            $File->setTitle($SessionUser->getUsername());
        }

        $SessionUser->setAttribute('avatar', $File->getUrl());
        $SessionUser->setAttribute(self::CLEANUP_ATTRIBUTE, array_keys($previousAvatars));
        $SessionUser->save();

        // Persist cleanup candidates with the new avatar, so failed deletions can
        // be retried without sweeping files belonging to an upload still in progress.
        foreach ($previousAvatars as $Avatar) {
            try {
                if ($this->canRemoveAvatar($Avatar, $UserFolder, $prefix)) {
                    $Avatar->delete($PermissionUser);
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addWarning('Avatar cleanup: ' . $Exception->getMessage());
            }
        }
    }

    private function canRemoveAvatar(QUI\Projects\Media\Image $Avatar, QUI\Projects\Media\Folder $Folder, string $prefix): bool
    {
        $Placeholder = $Folder->getMedia()->getPlaceholderImage();

        return !$Avatar->isDeleted()
            && $Avatar->getProject()->getName() === $Folder->getProject()->getName()
            && $Avatar->getParentId() === $Folder->getId()
            && str_starts_with((string)$Avatar->getAttribute('name'), $prefix)
            && (string)$Avatar->getAttribute('c_user') === (string)QUI::getUsers()->getSystemUser()->getUUID()
            && ($Placeholder === null || $Avatar->getUrl() !== $Placeholder->getUrl());
    }
}
