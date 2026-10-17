<?php

namespace QUI\FrontendUsers\Tests\Unit;

use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Events\Event;
use QUI\Events\Manager as EventManager;
use QUI\FrontendUsers\Events;
use QUI\FrontendUsers\Handler;
use QUI\Interfaces\Projects\Media\File;
use QUI\Projects\Manager as ProjectManager;
use QUI\Projects\Media;
use QUI\Projects\Media\Folder;
use QUI\Projects\Media\Image;
use QUI\Projects\Media\Utils as MediaUtils;
use QUI\Projects\Project;
use ReflectionProperty;

class AvatarPlaceholderTest extends TestCase
{
    private ?EventManager $OriginalEvents;
    private ?ProjectManager $OriginalProjectManager;
    private ?Project $OriginalStandard;
    private array $originalProjects;
    private array $originalUrlCache;
    private QUI\Config $Config;
    private mixed $originalDefaultAvatar;
    private Event $Events;
    private Image $Avatar;
    private Image $DefaultAvatar;
    private Image $Placeholder;
    private string $projectPlaceholderUrl;
    private static ?Image $ProviderAvatar = null;

    protected function setUp(): void
    {
        $this->OriginalEvents = QUI::$Events;
        $this->OriginalProjectManager = QUI::$ProjectManager;
        $this->OriginalStandard = ProjectManager::$Standard;
        $this->originalProjects = ProjectManager::$projects;
        $Cache = new ReflectionProperty(MediaUtils::class, 'urlItemCache');
        $this->originalUrlCache = $Cache->getValue();
        $Cache->setValue(null, []);
        $this->Config = Handler::getPackageConfig();
        $this->originalDefaultAvatar = $this->Config->get('profileBar', 'defaultAvatar');

        $this->Avatar = $this->createMock(Image::class);
        $this->DefaultAvatar = $this->createMock(Image::class);
        $this->Placeholder = $this->createMock(Image::class);
        $Folder = $this->createMock(Folder::class);
        $Project = $this->createMock(Project::class);
        $Project->method('getName')->willReturn('avatar-placeholder-test');
        $this->projectPlaceholderUrl = $this->imageUrl('valid', 3);
        $Project->method('getConfig')->with('placeholder')->willReturnCallback(
            fn(): string => $this->projectPlaceholderUrl
        );
        $Media = $this->getMockBuilder(Media::class)
            ->setConstructorArgs([$Project])
            ->onlyMethods(['get'])
            ->getMock();
        $Media->method('get')->willReturnCallback(function (int $id) use ($Folder): File {
            return match ($id) {
                1 => $this->Avatar,
                2 => $this->DefaultAvatar,
                3 => $this->Placeholder,
                4 => $Folder,
                default => throw new QUI\Exception('Avatar image not found')
            };
        });
        $Project->method('getMedia')->willReturn($Media);
        QUI::$ProjectManager = new ProjectManager();
        ProjectManager::$Standard = $Project;
        ProjectManager::$projects['avatar-placeholder-test']['_standard'] = $Project;

        $this->Events = new Event();
        $Document = new DOMDocument();
        self::assertTrue($Document->load(dirname(__DIR__, 2) . '/events.xml'));
        $registered = 0;

        foreach ($Document->getElementsByTagName('event') as $Declaration) {
            if ($Declaration->getAttribute('on') !== 'onUserGetAvatar') {
                continue;
            }

            $this->Events->addEvent(
                $Declaration->getAttribute('on'),
                $Declaration->getAttribute('fire'),
                (int)$Declaration->getAttribute('priority')
            );
            $registered++;
        }

        self::assertSame(1, $registered);
        QUI::$Events = new class ($this->Events) extends EventManager {
            public function __construct(Event $Events)
            {
                $this->Events = $Events;
            }
        };
    }

    protected function tearDown(): void
    {
        $this->Config->setValue('profileBar', 'defaultAvatar', $this->originalDefaultAvatar);
        QUI::$Events = $this->OriginalEvents;
        QUI::$ProjectManager = $this->OriginalProjectManager;
        ProjectManager::$Standard = $this->OriginalStandard;
        ProjectManager::$projects = $this->originalProjects;
        (new ReflectionProperty(MediaUtils::class, 'urlItemCache'))->setValue(null, $this->originalUrlCache);
        self::$ProviderAvatar = null;
    }

    public static function avatarFallbacks(): iterable
    {
        foreach (['valid', 'missing', 'empty', 'invalid', 'folder'] as $avatar) {
            foreach (['valid', 'missing', 'empty', 'invalid', 'icon', 'folder'] as $defaultAvatar) {
                yield $avatar . ' user image, ' . $defaultAvatar . ' default image' => [
                    $avatar,
                    $defaultAvatar
                ];
            }
        }
    }

    #[DataProvider('avatarFallbacks')]
    public function testGetAvatarPreservesUserImageAndFallsBackThroughConfiguredImages(
        string $avatar,
        string $defaultAvatar
    ): void {
        $this->Config->setValue('profileBar', 'defaultAvatar', $this->imageUrl($defaultAvatar, 2));
        $User = $this->createUser($this->imageUrl($avatar, 1));
        $Expected = $this->Placeholder;

        if ($avatar === 'valid') {
            $Expected = $this->Avatar;
        } elseif ($defaultAvatar === 'valid') {
            $Expected = $this->DefaultAvatar;
        }

        self::assertSame($Expected, $User->getAvatar());
        self::assertSame($this->imageUrl($avatar, 1), $User->getAttribute('avatar'));
    }

    public function testMissingProjectPlaceholderStillAllowsNull(): void
    {
        $this->Config->setValue('profileBar', 'defaultAvatar', '');
        $this->projectPlaceholderUrl = '';

        self::assertNull($this->createUser('')->getAvatar());
    }

    public function testAvatarProviderTakesPrecedenceOverTheDefaultImage(): void
    {
        $this->Config->setValue('profileBar', 'defaultAvatar', $this->imageUrl('valid', 2));
        self::$ProviderAvatar = $this->createMock(Image::class);
        // Register after the fallback to verify priority, rather than registration order.
        $this->Events->addEvent('onUserGetAvatar', self::class . '::provideAvatar');

        self::assertSame(self::$ProviderAvatar, $this->createUser('')->getAvatar());
    }

    public static function provideAvatar(QUI\Interfaces\Users\User $User): ?Image
    {
        return self::$ProviderAvatar;
    }

    public function testHandlerLeavesAnExistingUserImageToCore(): void
    {
        $this->Config->setValue('profileBar', 'defaultAvatar', $this->imageUrl('valid', 2));

        self::assertNull(Events::onUserGetAvatar($this->createUser($this->imageUrl('valid', 1))));
    }

    private function createUser(string $avatar): QUI\Users\User
    {
        $User = $this->getMockBuilder(QUI\Users\User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $User->method('getId')->willReturn(42);
        $User->setAttribute('avatar', $avatar);

        return $User;
    }

    private function imageUrl(string $state, int $id): string
    {
        return match ($state) {
            'valid' => 'image.php?project=avatar-placeholder-test&id=' . $id,
            'missing' => 'image.php?project=avatar-placeholder-test&id=99',
            'folder' => 'image.php?project=avatar-placeholder-test&id=4',
            'invalid' => 'invalid-image-url',
            'icon' => 'fa fa-user',
            default => ''
        };
    }
}
