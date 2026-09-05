<?php

namespace QUI\FrontendUsers\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\FrontendUsers\Controls\Profile\UserAvatarUpload;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\Projects\Media\Folder;
use QUI\Projects\Media\Image;
use ReflectionProperty;

class AvatarReplacementWorkflowTest extends DatabaseTestCase
{
    private Folder $Folder;
    private array $events;
    private array $projectConfig;
    private string $uploadDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        QUI\Cache\Manager::clear('quiqqer/users/user-extra-attributes');
        $this->events = QUI::getEvents()->getList();
        // External ERP/site hooks need tables outside the package's SQLite fixtures.
        foreach (['onUserCreate', 'onUserSaveBegin', 'onUserSave', 'onUserSaveEnd'] as $eventName) {
            foreach ($this->events[$eventName] ?? [] as $event) {
                if (!in_array($event['package'], ['quiqqer/core', 'quiqqer/frontend-users'], true)) {
                    QUI::getEvents()->removeEvent($eventName, $event['callable']);
                }
            }
        }

        $Project = QUI::getRewrite()->getProject();
        self::assertNotNull($Project);
        $this->Folder = $Project->getMedia()->firstChild()->createFolder(
            'phpunit-avatar-' . bin2hex(random_bytes(8)),
            QUI::getUsers()->getSystemUser()
        );
        // Use the same media instance that the uploader resolves from the configured URL.
        $Folder = QUI\Projects\Media\Utils::getMediaItemByUrl($this->Folder->getUrl());
        self::assertInstanceOf(Folder::class, $Folder);
        $this->Folder = $Folder;
        $this->projectConfig = $this->Folder->getProject()->getConfig();
        $this->setPlaceholder('');
        $this->setPackageConfig('userProfile', 'userAvatarFolder', $this->Folder->getUrl());
        $this->uploadDirectory = sys_get_temp_dir() . '/frontend-users-avatar-' . bin2hex(random_bytes(8));
        mkdir($this->uploadDirectory, 0700);
    }

    protected function tearDown(): void
    {
        $this->restoreEvents($this->events);
        (new ReflectionProperty(QUI\Projects\Project::class, 'config'))
            ->setValue($this->Folder->getProject(), $this->projectConfig);

        $System = QUI::getUsers()->getSystemUser();
        $Media = $this->Folder->getMedia();
        // Include deleted images: production uses the regular media trash lifecycle.
        $rows = self::getConnection()->fetchAllAssociative(
            'SELECT * FROM ' . QUI\Utils\Doctrine::quoteIdentifier($Media->getTable()) . ' WHERE type = ?',
            ['image']
        );
        foreach ($rows as $row) {
            $Item = $Media->parseResultToItem($row);
            if ($Item instanceof Image && str_starts_with($Item->getFullPath(), rtrim($this->Folder->getFullPath(), '/') . '/')) {
                if (!$Item->isDeleted()) {
                    $Item->delete($System);
                }
                $Item->destroy($System);
            }
        }
        $this->Folder->delete($System);
        $this->Folder->destroy($System);
        foreach (glob($this->uploadDirectory . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->uploadDirectory);
        parent::tearDown();
    }

    public function testRepeatedReplacementWithoutPlaceholderRemovesPreviousImages(): void
    {
        $User = $this->createUser(true);
        $First = $this->upload($User);
        $firstPath = $First->getFullPath();
        $Second = $this->upload($User);
        self::assertTrue($First->isDeleted());
        self::assertFileDoesNotExist($firstPath);
        self::assertFalse($Second->isDeleted());
        self::assertTrue($Second->isActive());

        $Third = $this->upload($User);
        self::assertTrue($Second->isDeleted());
        self::assertSame($Third->getUrl(), $User->getAttribute('avatar'));
        self::assertCount(1, $this->Folder->getChildren());
    }

    public function testOwnedImageUsedAsPlaceholderIsPreserved(): void
    {
        $User = $this->createUser(true);
        $Placeholder = $this->upload($User);
        $this->setPlaceholder($Placeholder->getUrl());
        self::assertSame($Placeholder->getUrl(), $this->Folder->getMedia()->getPlaceholderImage()?->getUrl());
        $this->upload($User);
        self::assertFalse($Placeholder->isDeleted());
        self::assertFileExists($Placeholder->getFullPath());
    }

    public function testAnotherUsersAvatarIsPreserved(): void
    {
        $OtherUser = $this->createUser(true);
        $Foreign = $this->upload($OtherUser);
        $User = $this->createUser(true);
        $User->setAttribute('avatar', $Foreign->getUrl());
        $User->save();
        $this->upload($User);
        self::assertFalse($Foreign->isDeleted());
        self::assertFileExists($Foreign->getFullPath());
    }

    public function testUnmarkedLegacyImageIsPreservedEvenWithPlaceholder(): void
    {
        $User = $this->createUser(true);
        $Legacy = $this->Folder->uploadFile($this->imageFile(), Folder::FILE_OVERWRITE_TRUE);
        $Legacy->activate(QUI::getUsers()->getSystemUser());
        $Placeholder = $this->upload($this->createUser(true));
        $this->setPlaceholder($Placeholder->getUrl());
        $User->setAttribute('avatar', $Legacy->getUrl());
        $User->save();
        $this->upload($User);
        self::assertFalse($Legacy->isDeleted());
        self::assertFileExists($Legacy->getFullPath());
    }

    public function testOwnedImageOutsideConfiguredFolderIsPreserved(): void
    {
        $User = $this->createUser(true);
        $Previous = $this->upload($User);
        $OtherFolder = $this->Folder->createFolder('outside');
        try {
            $Previous->moveTo($OtherFolder, QUI::getUsers()->getSystemUser());
            $User->setAttribute('avatar', $Previous->getUrl());
            $User->save();
            $this->upload($User);
            self::assertFalse($Previous->isDeleted());
            self::assertFileExists($Previous->getFullPath());
        } finally {
            $Previous->delete(QUI::getUsers()->getSystemUser());
            $Previous->destroy(QUI::getUsers()->getSystemUser());
            $OtherFolder->delete(QUI::getUsers()->getSystemUser());
        }
    }

    public static function failureStages(): array
    {
        return [['upload'], ['activation'], ['save']];
    }

    #[DataProvider('failureStages')]
    public function testFailedReplacementPreservesPreviousAvatar(string $stage): void
    {
        $User = $this->createUser(true);
        $Previous = $this->upload($User);
        $Placeholder = $this->upload($this->createUser(true));
        $this->setPlaceholder($Placeholder->getUrl());
        $file = $this->imageFile();
        $event = match ($stage) {
            'upload' => 'onMediaSaveBegin',
            'activation' => 'onMediaActivate',
            'save' => 'onUserSaveBegin'
        };
        QUI::getEvents()->addEvent($event, static function (): void {
            throw new QUI\Exception('Simulated avatar replacement failure');
        });

        try {
            (new UserAvatarUpload(['User' => $User]))->onFileFinish($file, []);
            self::fail('The replacement must fail.');
        } catch (QUI\Exception) {
            self::assertFalse($Previous->isDeleted());
            self::assertTrue($Previous->isActive());
            self::assertFileExists($Previous->getFullPath());
            $avatar = self::getConnection()->fetchOne(
                'SELECT avatar FROM ' . QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName('users'))
                . ' WHERE uuid = :uuid',
                ['uuid' => $User->getUUID()]
            );
            self::assertSame($Previous->getUrl(), $avatar);
        }
    }

    public function testCleanupFailureIsRetriedOnNextSuccessfulUpload(): void
    {
        $User = $this->createUser(true);
        $Previous = $this->upload($User);
        $Fail = static function (): void {
            throw new QUI\Exception('Simulated media cleanup failure');
        };
        QUI::getEvents()->addEvent('onMediaDeleteBegin', $Fail);
        $Current = $this->upload($User);
        self::assertFalse($Previous->isDeleted());
        self::assertSame($Current->getUrl(), $User->getAttribute('avatar'));
        QUI::getEvents()->removeEvent('onMediaDeleteBegin', $Fail);
        $User = new QUI\Users\User($User->getUUID());
        self::assertContains($Previous->getUrl(), $User->getAttribute('quiqqer.frontendUsers.avatarCleanup'));
        $this->upload($User);
        self::assertTrue($Previous->isDeleted());
        self::assertTrue($Current->isDeleted());
        self::assertCount(1, $this->Folder->getChildren());
    }

    public function testCleanupListCannotDeleteForeignImages(): void
    {
        $User = $this->createUser(true);
        $Foreign = $this->upload($this->createUser(true));
        $User->setAttribute('quiqqer.frontendUsers.avatarCleanup', [$Foreign->getUrl(), [], null]);
        $this->upload($User);
        self::assertFalse($Foreign->isDeleted());
        self::assertSame([], $User->getAttribute('quiqqer.frontendUsers.avatarCleanup'));
    }

    public function testCleanupDoesNotSweepAnUploadStillInProgress(): void
    {
        $User = $this->createUser(true);
        $Previous = $this->upload($User);
        $file = $this->uploadDirectory . '/frontend-users-avatar-'
            . hash('sha256', (string)$User->getUUID()) . '-' . QUI\Utils\Uuid::get() . '.png';
        rename($this->imageFile(), $file);
        $InProgress = $this->Folder->uploadFile($file, Folder::FILE_OVERWRITE_TRUE);
        // Another request has uploaded its file but has not persisted its avatar yet.
        $this->upload($User);
        self::assertTrue($Previous->isDeleted());
        self::assertFalse($InProgress->isDeleted());
        self::assertFileExists($InProgress->getFullPath());
    }

    private function upload(QUI\Interfaces\Users\User $User): Image
    {
        (new UserAvatarUpload(['User' => $User]))->onFileFinish($this->imageFile(), []);
        return QUI\Projects\Media\Utils::getImageByUrl($User->getAttribute('avatar'));
    }

    private function imageFile(): string
    {
        $file = $this->uploadDirectory . '/' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($file, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));
        return $file;
    }

    private function setPlaceholder(string $url): void
    {
        (new ReflectionProperty(QUI\Projects\Project::class, 'config'))->setValue(
            $this->Folder->getProject(),
            array_replace($this->projectConfig, ['placeholder' => $url])
        );
    }
}
