<?php

/**
 * Namespace QUI\FrontendUsers\AbstractRegistrar
 */

namespace QUI\FrontendUsers;

use QUI;

/**
 * Class AbstractRegistrar
 *
 * @package QUI\FrontendUsers
 */
abstract class AbstractRegistrar extends QUI\QDOM implements RegistrarInterface
{
    /**
     * @var QUI\Projects\Project
     */
    protected $Project = null;

    /**
     * @return InvalidFormField[]
     */
    abstract public function validate();

    /**
     * Get all invalid registration form fields
     *
     * @return InvalidFormField[]
     */
    abstract public function getInvalidFields();

    /**
     * @return mixed
     */
    abstract public function getUsername();

    /**
     * @return mixed
     */
    abstract public function getControl();

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return integer
     */
    abstract public function onRegistered(QUI\Interfaces\Users\User $User);

    /**
     * Get title
     *
     * @param QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    abstract public function getTitle($Locale = null);

    /**
     * Get description
     *
     * @param QUI\Locale $Locale (optional) - If omitted use QUI::getLocale()
     * @return string
     */
    abstract public function getDescription($Locale = null);

    /**
     * Return an icon for the registratar
     *
     * @return string
     */
    abstract public function getIcon();

    /**
     * Return the success message
     *
     * @return string
     */
    public function getSuccessMessage()
    {
        $registrarSettings = $this->getSettings();
        $settings          = Handler::getInstance()->getRegistrationSettings();

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

        if (boolval($settings['sendPassword']) && $this->canSendPassword()) {
            $msg .= "<p>".
                    QUI::getLocale()->get('quiqqer/frontend-users', 'registrars.password_auto_generate').
                    "</p>";
        }

        return $msg;
    }

    /**
     * @return string
     */
    public function getPendingMessage()
    {
        $msg = QUI::getLocale()->get('quiqqer/frontend-users', 'message.registration_pending');
//        $settings = Handler::getInstance()->getRegistrationSettings();
//
//        if (boolval($settings['sendPassword'])) {
//            $msg .= "<p>" . QUI::getLocale()->get(
//                    'quiqqer/frontend-users',
//                    'registrars.email.password_auto_generate'
//                ) . "</p>";
//        }

        return $msg;
    }

    /**
     * Get message for registration errors
     * @return string
     */
    public function getErrorMessage()
    {
        return QUI::getLocale()->get('quiqqer/frontend-users', 'message.registration_error');
    }

    /**
     * Create a new user
     *
     * @return QUI\Users\User
     * @throws QUI\Exception
     */
    public function createUser()
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
    public function setProject(QUI\Projects\Project $Project)
    {
        $this->Project = $Project;
    }

    /**
     * Get current Project the Registrar works for
     *
     * @return QUI\Projects\Project|null
     */
    public function getProject()
    {
        return $this->Project;
    }

    /**
     * Get registrar settings
     *
     * @return array
     */
    public function getSettings()
    {
        return Handler::getInstance()->getRegistrarSettings($this->getType());
    }

    /**
     * Get unique hash that identifies the Registrar
     *
     * @return string
     */
    public function getHash()
    {
        return hash('sha256', $this->getType());
    }

    /**
     * Check if this Registrar can send passwords
     *
     * @return bool
     */
    abstract public function canSendPassword();

    /**
     * Check if this Registrar is activated in the settings
     *
     * @return bool
     */
    public function isActive()
    {
        $Handler           = Handler::getInstance();
        $registrarSettings = $Handler->getRegistrarSettings();
        $type              = $this->getType();

        if (empty($registrarSettings[$type]['active'])) {
            return false;
        }

        return boolval($registrarSettings[$type]['active']);
    }
}
