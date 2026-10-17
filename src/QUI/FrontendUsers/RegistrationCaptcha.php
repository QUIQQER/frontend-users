<?php

namespace QUI\FrontendUsers;

use QUI;
use Throwable;

/**
 * Shared CAPTCHA capability check for registration and its settings.
 */
class RegistrationCaptcha
{
    /**
     * Resolve the configured module without rendering a new challenge.
     *
     * @throws Exception
     */
    public static function assertAvailable(): QUI\Control
    {
        try {
            if (
                !Utils::isCaptchaModuleInstalled()
                || !class_exists(QUI\Captcha\Handler::class)
                || !class_exists(QUI\Captcha\Controls\CaptchaDisplay::class)
            ) {
                throw new \RuntimeException('CAPTCHA components are unavailable.');
            }

            return QUI\Captcha\Handler::getDefaultCaptchaModuleControl();
        } catch (Throwable) {
            throw new Exception([
                'quiqqer/frontend-users',
                'exception.registrars.email.captcha_unavailable'
            ]);
        }
    }

    /**
     * The Core dispatches this event after saving. Keep the policy enabled so
     * a broken configuration cannot silently disable registration protection.
     *
     * @param array<string, mixed> $params
     */
    public static function onPackageConfigSave(QUI\Package\Package $Package, array $params): void
    {
        if (
            $Package->getName() !== 'quiqqer/frontend-users'
            || empty($params['registration']['useCaptcha'])
            || !$Package->getConfig()?->get('registration', 'useCaptcha')
        ) {
            return;
        }

        try {
            self::assertAvailable();
        } catch (Exception $Exception) {
            QUI::getMessagesHandler()->addError($Exception->getMessage());
        }
    }
}
