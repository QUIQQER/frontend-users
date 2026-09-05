<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Interfaces\Users\User;
use QUI\Projects\Project;

/**
 * Registration rules shared by browser and REST entry points.
 */
class RegistrationPolicy
{
    public function validate(RegistrarInterface $Registrar): void
    {
        if (!$Registrar->isActive()) {
            throw new Exception([
                'quiqqer/frontend-users',
                'exception.registration.registrar_not_found'
            ]);
        }

        $settings = Handler::getInstance()->getRegistrationSettings();

        if (!empty($settings['termsOfUseRequired']) && !$Registrar->getAttribute('termsOfUseAccepted')) {
            throw new Exception([
                'quiqqer/frontend-users',
                'exception.registration.terms_of_use_not_accepted'
            ]);
        }

        $Registrar->validate();
    }

    public function setUserAttributes(User $User, RegistrarInterface $Registrar, Project $Project): void
    {
        $User->setAttributes([
            Handler::USER_ATTR_REGISTRATION_PROJECT => $Project->getName(),
            Handler::USER_ATTR_REGISTRATION_PROJECT_LANG => $Project->getLang(),
            Handler::USER_ATTR_REGISTRAR => $Registrar->getType(),
            Handler::USER_ATTR_USER_ACTIVATION_REQUIRED => true
        ]);
    }

    /**
     * @param null|callable(): bool $sendActivationMail Existing transport-specific mail hook.
     */
    public function activate(
        User $User,
        RegistrarInterface $Registrar,
        Project $Project,
        ?callable $sendActivationMail = null
    ): int {
        $Handler = Handler::getInstance();
        $settings = $Handler->getRegistrarSettings($Registrar->getType());

        switch ($settings['activationMode']) {
            case Handler::ACTIVATION_MODE_MAIL:
                $success = $sendActivationMail !== null
                    ? $sendActivationMail()
                    : $Handler->sendActivationMail($User, $Registrar);

                if (!$success) {
                    throw new Exception([
                        'quiqqer/frontend-users',
                        'exception.registration.send_mail_error'
                    ]);
                }

                return Handler::REGISTRATION_STATUS_PENDING;

            case Handler::ACTIVATION_MODE_AUTO:
            case Handler::ACTIVATION_MODE_AUTO_WITH_EMAIL_CONFIRM:
                if (!$User->isActive()) {
                    $User->activate('', QUI::getUsers()->getSystemUser());
                }

                if ($settings['activationMode'] === Handler::ACTIVATION_MODE_AUTO_WITH_EMAIL_CONFIRM) {
                    $Handler->sendEmailConfirmationMail($User, $User->getAttribute('email'), $Project);
                }
                break;
        }

        return Handler::REGISTRATION_STATUS_SUCCESS;
    }
}
