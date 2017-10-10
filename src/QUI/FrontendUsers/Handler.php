<?php

/**
 * This file contains QUI\FrontendUsers\Handler
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\Utils\Singleton;
use QUI\Verification\Verifier;

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
     * @var null|RegistratorCollection
     */
    protected $Registrator = null;

    /**
     * Handler constructor.
     */
    public function __construct()
    {
        $this->Registrator = new RegistratorCollection();
    }

    /**
     * @return RegistratorCollection
     */
    public function getRegistrators()
    {
        $Registrators      = new RegistratorCollection();
        $Available         = $this->getAvailableRegistrators();
        $registrarSettings = $this->getRegistrarSettings();

        /** @var AbstractRegistrator $Registrator */
        foreach ($Available as $Registrator) {
            $t = $Registrator->getType();

            if (isset($registrarSettings[$t])
                && !$registrarSettings[$t]['active']) {
                continue;
            }

            $Registrators->append($Registrator);
        }

        return $Registrators;
    }

    /**
     * Return all available registrator
     *
     * @return RegistratorCollection
     */
    public function getAvailableRegistrators()
    {
        if ($this->Registrator->isEmpty() !== false) {
            return $this->Registrator;
        }

        $list      = array();
        $installed = QUI::getPackageManager()->getInstalled();

        foreach ($installed as $package) {
            try {
                $Package = QUI::getPackage($package['name']);

                if (!$Package->isQuiqqerPackage()) {
                    continue;
                }

                $list = array_merge($list, $Package->getProvider('registrator'));
            } catch (QUI\Exception $exception) {
            }
        }

        foreach ($list as $provider) {
            try {
                if (!class_exists($provider)) {
                    continue;
                }

                $this->Registrator->append(new $provider());
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $this->Registrator;
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
     * @return void
     */
    public function sendActivationMail(QUI\Users\User $User)
    {
        $MailVerification = new MailVerification($User->getId());
        $activationLink   = Verifier::startVerification($MailVerification);

        // todo
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
