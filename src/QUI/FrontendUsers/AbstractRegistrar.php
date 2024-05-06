<?php

/**
 * Namespace QUI\FrontendUsers\AbstractRegistrar
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\Exception;

use function mb_strlen;

/**
 * Class AbstractRegistrar
 *
 * @package QUI\FrontendUsers
 */
abstract class AbstractRegistrar extends QUI\QDOM implements RegistrarInterface
{
    /**
     * @var ?QUI\Projects\Project
     */
    protected ?QUI\Projects\Project $Project = null;

    /**
     * @return InvalidFormField[]
     */
    abstract public function validate(): array;

    /**
     * Get all invalid registration form fields
     *
     * @return InvalidFormField[]
     */
    abstract public function getInvalidFields(): array;

    /**
     * @return mixed
     */
    abstract public function getUsername(): string;

    /**
     * @return QUI\Control
     */
    abstract public function getControl(): QUI\Control;

    /**
     * @param QUI\Interfaces\Users\User $User
     */
    abstract public function onRegistered(QUI\Interfaces\Users\User $User): void;

    /**
     * Get title
     *
     * @param QUI\Locale|null $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    abstract public function getTitle(QUI\Locale $Locale = null): string;

    /**
     * Get description
     *
     * @param QUI\Locale|null $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    abstract public function getDescription(QUI\Locale $Locale = null): string;

    /**
     * Return an icon for the registrar
     *
     * @return string
     */
    abstract public function getIcon(): string;

    /**
     * Return the success message
     *
     * @return string
     * @throws Exception
     */
    public function getSuccessMessage(): string
    {
        $registrarSettings = $this->getSettings();
        $settings = Handler::getInstance()->getRegistrationSettings();

        switch ($registrarSettings['activationMode']) {
            case Handler::ACTIVATION_MODE_MANUAL:
                $msg = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'message.registrars.registration_success_manual'
                );
                break;

            case Handler::ACTIVATION_MODE_AUTO:
                $msg = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'message.registrars.registration_success_auto'
                );
                break;

            case Handler::ACTIVATION_MODE_MAIL:
                $msg = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'message.registrars.registration_success_mail'
                );
                break;

            default:
                return $this->getPendingMessage();
        }

        if ($settings['sendPassword'] && $this->canSendPassword()) {
            $msg .= "<p>" .
                QUI::getLocale()->get('quiqqer/frontend-users', 'registrars.password_auto_generate') .
                "</p>";
        }

        return $msg;
    }

    /**
     * @return string
     */
    public function getPendingMessage(): string
    {
        return QUI::getLocale()->get('quiqqer/frontend-users', 'message.registration_pending');
    }

    /**
     * Get message for registration errors
     * @return string
     */
    public function getErrorMessage(): string
    {
        return QUI::getLocale()->get('quiqqer/frontend-users', 'message.registration_error');
    }

    /**
     * Create a new user
     *
     * @return QUI\Interfaces\Users\User
     * @throws QUI\Exception
     */
    public function createUser(): QUI\Interfaces\Users\User
    {
        return QUI::getUsers()->createChild(
            $this->getUsername(),
            QUI::getUsers()->getSystemUser()
        );
    }

    /**
     * Set current Project the Registrar works for
     *
     * @param QUI\Projects\Project $Project
     * @return void
     */
    public function setProject(QUI\Projects\Project $Project): void
    {
        $this->Project = $Project;
    }

    /**
     * Get current Project the Registrar works for
     *
     * @return QUI\Projects\Project|null
     */
    public function getProject(): ?QUI\Projects\Project
    {
        return $this->Project;
    }

    /**
     * Get registrar settings
     *
     * @return array
     */
    public function getSettings(): array
    {
        return Handler::getInstance()->getRegistrarSettings($this->getType());
    }

    /**
     * Get unique hash that identifies the Registrar
     *
     * @return string
     */
    public function getHash(): string
    {
        return hash('sha256', $this->getType());
    }

    /**
     * Check if this Registrar can send passwords
     *
     * @return bool
     */
    abstract public function canSendPassword(): bool;

    /**
     * Check if this Registrar is activated in the settings
     *
     * @return bool
     */
    public function isActive(): bool
    {
        $Handler = Handler::getInstance();
        $registrarSettings = $Handler->getRegistrarSettings();
        $type = $this->getType();

        if (empty($registrarSettings[$type]['active'])) {
            return false;
        }

        return boolval($registrarSettings[$type]['active']);
    }

    /**
     * Validates all user attributes
     *
     * @return void
     * @throws Exception
     */
    public function checkUserAttributes(): void
    {
        $Handler = Handler::getInstance();

        // Length check
        foreach ($Handler->getUserAttributeLengthRestrictions() as $attribute => $maxLength) {
            $value = $this->getAttribute($attribute);

            if (empty($value)) {
                continue;
            }

            if (mb_strlen($value) > $maxLength) {
                throw new Exception([
                    'quiqqer/frontend-users',
                    'exception.registrars.email.user_attribute_too_long',
                    [
                        'label' => QUI::getLocale()->get('quiqqer/system', $attribute),
                        'maxLength' => $maxLength
                    ]
                ]);
            }
        }
    }
}
