<?php

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\Verification\Interface\VerificationRepositoryInterface;
use QUI\Verification\VerificationRepository;

class TwoFactorAuthentication extends AbstractProfileControl
{
    public function __construct(
        array $attributes = [],
        private ?VerificationRepositoryInterface $verificationRepository = null
    ) {
        if (is_null($this->verificationRepository)) {
            $this->verificationRepository = new VerificationRepository();
        }

        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-userdata');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');
        $this->addCSSFile(__DIR__ . '/TwoFactorAuthentication.css');

        $this->setJavaScriptControl(
            'package/quiqqer/frontend-users/bin/frontend/controls/profile/TwoFactorAuthentication'
        );
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        $User = QUI::getUserBySession();
        $Auth = QUI\Users\Auth\Handler::getInstance();
        $Engine = QUI::getTemplateManager()->getEngine();
        $Config = QUI::getConfig('etc/conf.ini.php');

        $twoFactorAuthIsEnabled = true;

        if ($Config->getValue('auth_settings', 'secondary_frontend')) {
            $twoFactorAuthIsEnabled = $Config->getValue('auth_settings', 'secondary_frontend');
        }

        $authenticators = [];

        foreach ($Auth->getGlobalFrontendSecondaryAuthenticators() as $authClass) {
            try {
                if (class_exists($authClass)) {
                    $authenticators[] = new $authClass($User->getUUID());
                }
            } catch (QUI\Exception) {
            }
        }

        $Engine->assign([
            'twoFactorAuthIsEnabled' => $twoFactorAuthIsEnabled,
            'authenticators' => $authenticators
        ]);

        return $Engine->fetch($this->getTemplateFile());
    }

    /**
     * event: on save
     */
    public function onSave(): void
    {
    }
}
