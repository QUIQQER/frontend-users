<?php

/**
 * This file contains QUI\FrontendUsers\Handler
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\Utils\Singleton;
use QUI\Verification\Verifier;
use QUI\Mail\Mailer;

/**
 * Class Registration Handling
 * - Main Registration Handler
 *
 * @package QUI\FrontendUsers
 */
class Handler extends Singleton
{
    /**
     * Registration statuses
     */
    const REGISTRATION_STATUS_ERROR   = 0;
    const REGISTRATION_STATUS_SUCCESS = 1;
    const REGISTRATION_STATUS_PENDING = 2;

    /**
     * Activation modes
     */
    const ACTIVATION_MODE_MAIL   = 'mail';
    const ACTIVATION_MODE_AUTO   = 'auto';
    const ACTIVATION_MODE_MANUAL = 'manual';

    /**
     * Site types
     */
    const SITE_TYPE_REGISTRATION = 'quiqqer/frontend-users:types/registration';
    const SITE_TYPE_LOGIN        = 'quiqqer/frontend-users:types/login';
    const SITE_TYPE_PROFILE      = 'quiqqer/frontend-users:types/profile';

    /**
     * Misc
     */
    const SESSION_REGISTRAR = 'frontend_users_registrar';

    /**
     * @var null|RegistrarCollection
     */
    protected $Registrar = null;

    /**
     * Handler constructor.
     */
    public function __construct()
    {
        $this->Registrar = new RegistrarCollection();
    }

    /**
     * @return RegistrarCollection
     */
    public function getRegistrars()
    {
        $Registrars      = new RegistrarCollection();
        $Available         = $this->getAvailableRegistrars();
        $registrarSettings = $this->getRegistrarSettings();

        /** @var AbstractRegistrar $Registrar */
        foreach ($Available as $Registrar) {
            $t = $Registrar->getType();

            if (isset($registrarSettings[$t])
                && !$registrarSettings[$t]['active']) {
                continue;
            }

            $Registrars->append($Registrar);
        }

        return $Registrars;
    }

    /**
     * Get specific Registrar
     *
     * @param string $registrar - Registrar
     * @return false|AbstractRegistrar
     */
    public function getRegistrar($registrar)
    {
        /** @var AbstractRegistrar $Registrar */
        foreach ($this->getAvailableRegistrars() as $Registrar)
        {
            if ($Registrar->getType() === $registrar) {
                return $Registrar;
            }
        }

        return false;
    }

    /**
     * Return all available registrar
     *
     * @return RegistrarCollection
     */
    public function getAvailableRegistrars()
    {
        if ($this->Registrar->isEmpty() !== false) {
            return $this->Registrar;
        }

        $list      = array();
        $installed = QUI::getPackageManager()->getInstalled();

        foreach ($installed as $package) {
            try {
                $Package = QUI::getPackage($package['name']);

                if (!$Package->isQuiqqerPackage()) {
                    continue;
                }

                $list = array_merge($list, $Package->getProvider('registrar'));
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        foreach ($list as $provider) {
            try {
                if (!class_exists($provider)) {
                    continue;
                }

                $this->Registrar->append(new $provider());
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $this->Registrar;
    }

    /**
     * Get registration settings concerning all Registars alike
     *
     * @return array
     */
    public function getRegistrationSettings()
    {
        $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        return $Conf->getSection('registration');
    }

    /**
     * Get settings for one or all Registrars
     *
     * @param string $registrarClass (optional) - Registar class path (namespace)
     * @return array
     */
    public function getRegistrarSettings($registrarClass = null)
    {
        $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();

        $registrarSettings = $Conf->get('registrars', 'registrarSettings');
        $registrarSettings = json_decode($registrarSettings, true);

        foreach ($registrarSettings as $type => $settings) {
            unset($registrarSettings[$type]);

            $type                     = base64_decode($type);
            $registrarSettings[$type] = $settings;
        }

        if (!is_null($registrarClass)
            && isset($registrarSettings[$registrarClass])) {
            return $registrarSettings[$registrarClass];
        }

        return $registrarSettings;
    }

    /**
     * Send activtion mail for a user account
     *
     * @param QUI\Users\User $User
     * @param AbstractRegistrar $Registrar
     * @return void
     */
    public function sendActivationMail(QUI\Users\User $User, AbstractRegistrar $Registrar)
    {
        $Project = $Registrar->getProject();

        $MailVerification = new MailVerification($User->getId(), array(
            'project'     => $Project->getName(),
            'projectLang' => $Project->getLang(),
            'registrar' => $Registrar->getType()
        ));

        $activationLink = Verifier::startVerification($MailVerification);

        $L      = QUI::getLocale();
        $lg     = 'quiqqer/frontend-users';
        $tplDir = QUI::getPackage('quiqqer/frontend-users')->getDir() . 'templates/';
        $host   = $Project->getVHost();

        try {
            $this->sendMail(
                $L->get($lg, 'mail.registration_activation.subject', array(
                    'host' => $host
                )),
                array(
                    $User->getAttribute('email')
                ),
                $tplDir . 'mail.registration_activation.html',
                array(
                    'body' => $L->get($lg, 'mail.registration_activation.body', array(
                        'host'           => $host,
                        'userId'         => $User->getId(),
                        'username'       => $User->getUsername(),
                        'email'          => $User->getAttribute('email'),
                        'date'           => $L->formatDate(time()),
                        'activationLink' => $activationLink
                    ))
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: sendActivationMail -> Send mail failed'
            );

            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Send information about a new registration
     *
     * @param QUI\Users\User $User
     * @param QUI\Projects\Project $Project
     * @return void
     */
    public function sendRegistrationNotice(QUI\Users\User $User, $Project)
    {
        $registrationSettings = $this->getRegistrationSettings();
        $recipients           = explode(",", $registrationSettings['sendInfoMailOnRegistrationTo']);

        $L      = QUI::getLocale();
        $lg     = 'quiqqer/frontend-users';
        $tplDir = QUI::getPackage('quiqqer/frontend-users')->getDir() . 'templates/';
        $host   = $Project->getVHost();

        try {
            $this->sendMail(
                $L->get($lg, 'mail.registration_notice.subject', array(
                    'host' => $host
                )),
                $recipients,
                $tplDir . 'mail.registration_notice.html',
                array(
                    'body' => $L->get($lg, 'mail.registration_notice.body', array(
                        'host'     => $host,
                        'userId'   => $User->getId(),
                        'username' => $User->getUsername(),
                        'email'    => $User->getAttribute('email'),
                        'date'     => $L->formatDate(time())
                    ))
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: sendRegistrationNotice -> Send mail failed'
            );

            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Send an email to the membership user
     *
     * @param string $subject - mail subject
     * @param array $recipients - e-mail addresses
     * @param string $templateFile
     * @param array $templateVars (optional) - additional template variables (besides $this)
     * @return void
     *
     * @throws QUI\Memberships\Exception
     */
    protected function sendMail($subject, $recipients, $templateFile, $templateVars = array())
    {
        if (empty($recipients)) {
            return;
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign($templateVars);

        $template = $Engine->fetch($templateFile);
        $Mailer   = new Mailer();

        foreach ($recipients as $recipient) {
            $Mailer->addRecipient($recipient);
        }

        $Mailer->setSubject($subject);
        $Mailer->setBody($template);
        $Mailer->send();
    }

    /**
     * Get ACTIVE registration site for a project
     *
     * @param QUI\Projects\Project $Project (optional) - if omitted use default project
     * @return QUI\Projects\Site|false - Site object or false if no ACTIVE registration site found
     */
    public function getRegistrationSite($Project = null)
    {
        if (is_null($Project)) {
            $Project = QUI::getProjectManager()->getStandard();
        }

        $result = $Project->getSites(array(
            'where' => array(
                'type' => self::SITE_TYPE_REGISTRATION
            ),
            'limit' => 1
        ));

        if (empty($result)) {
            return false;
        }

        return current($result);
    }
}
