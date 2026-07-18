<?php

namespace QUI\FrontendUsers\Tests\Integration;

use QUI;
use QUI\FrontendUsers\Controls\Address\Address;
use QUI\FrontendUsers\Controls\Auth\FrontendLogin;
use QUI\FrontendUsers\Controls\Bricks\AuthContent;
use QUI\FrontendUsers\Controls\Login;
use QUI\FrontendUsers\Controls\Profile;
use QUI\FrontendUsers\Controls\Profile\Address as ProfileAddress;
use QUI\FrontendUsers\Controls\Profile\ChangePassword;
use QUI\FrontendUsers\Controls\Profile\DeleteAccount;
use QUI\FrontendUsers\Controls\Profile\TwoFactorAuthentication;
use QUI\FrontendUsers\Controls\Profile\UserAvatar;
use QUI\FrontendUsers\Controls\Profile\UserData;
use QUI\FrontendUsers\Controls\ProfileBar;
use QUI\FrontendUsers\Controls\Registration;
use QUI\FrontendUsers\Controls\RegistrationSignUp;
use QUI\FrontendUsers\Controls\UserIcon;
use QUI\FrontendUsers\Registrars\Email\Control as EmailControl;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;

class ControlRenderingTest extends DatabaseTestCase
{
    public function testAuthenticatedProfileControlsRenderAgainstRealUser(): void
    {
        $User = $this->createUser(true);
        self::replaceSessionUser($User);

        $controls = [
            new ProfileBar(),
            new UserIcon(),
            new Profile(),
            new Address(),
            new ProfileAddress(),
            new UserData(),
            new ChangePassword(),
            new DeleteAccount(),
            new TwoFactorAuthentication(),
            new UserAvatar()
        ];

        foreach ($controls as $Control) {
            $body = $Control->getBody();
            self::assertIsString($body, $Control::class);
        }

        self::assertSame('Address', (new Address())->getName());
        self::assertSame('fa-address-card', (new Address())->getIcon());
    }

    public function testAnonymousRegistrationAndLoginControlsRender(): void
    {
        self::replaceSessionUser(QUI::getUsers()->getNobody());
        $_REQUEST = [];
        $_POST = [];

        $controls = [
            new FrontendLogin(),
            new Login(),
            new Registration(),
            new RegistrationSignUp(),
            new EmailControl(),
            new AuthContent()
        ];

        foreach ($controls as $Control) {
            $body = $Control->getBody();
            self::assertIsString($body, $Control::class);
        }

        self::assertIsString((new EmailControl())->renderAddress());
    }
}
