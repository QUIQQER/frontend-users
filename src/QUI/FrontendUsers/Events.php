<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Users\User;
use QUI\Verification\Verifier;

/**
 * Class Events
 *
 * General events class for quiqqer/frontend-users
 *
 * @package QUI\FrontendUsers
 */
class Events
{
    /**
     * quiqqer/quiqqer: onUserActivate
     *
     * @param \QUI\Users\User $User
     * @return void
     */
    public static function onUserActivate(User $User)
    {
        self::sendWelcomeMail($User);
        self::autoLogin($User);
    }

    /**
     * Send welcome mail to the user
     *
     * @param User $User
     * @return void
     */
    protected static function sendWelcomeMail(User $User)
    {
        $Handler              = Handler::getInstance();
        $registrationSettings = $Handler->getRegistrationSettings();

        if (!$registrationSettings['userWelcomeMail']
            || $User->getAttribute('quiqqer.frontendUsers.welcomeMailSent')) {
            return;
        }

        // check if user registered himself
        $project     = $User->getAttribute($Handler::USER_ATTR_REGISTRATION_PROJECT);
        $projectLang = $User->getAttribute($Handler::USER_ATTR_REGISTRATION_PROJECT_LANG);

        // if no project data was set to the user this means the user
        // was created manually (by an administrator)
        if (empty($project) || empty($projectLang)) {
            return;
        }

        // set random password
        $randomPass = null;

        if ($registrationSettings['passwordInput'] === $Handler::PASSWORD_INPUT_SENDMAIL) {
            $randomPass = QUI\Security\Password::generateRandom();
            $User->setPassword($randomPass, QUI::getUsers()->getSystemUser());
            $User->save(QUI::getUsers()->getSystemUser());
        }

        // send welcome mail
        $Project = QUI::getProjectManager()->getProject($project, $projectLang);
        $Handler->sendWelcomeMail($User, $Project, $randomPass);
    }

    /**
     * Auto-login user
     *
     * @param User $User
     * @return void
     */
    public static function autoLogin(User $User)
    {
        $Handler   = Handler::getInstance();
        $registrar = $User->getAttribute($Handler::USER_ATTR_REGISTRAR);

        if (empty($registrar)) {
            return;
        }

        // check if Registrar exists
        try {
            $Registrar = $Handler->getRegistrar($registrar);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return;
        }

        $registrationSettings = $Handler->getRegistrationSettings();

        // do not log in if autoLogin is deactivated or user is already logged in!
        if (!$registrationSettings['autoLoginOnActivation']
            || QUI::getUserBySession()->getId()
            || $User->getAttribute($Handler::USER_ATTR_ACTIVATION_LOGIN_EXECUTED)) {
            return;
        }

        $settings = $Handler->getRegistrarSettings($Registrar->getType());

        // do not log in if users have to be manually activated
        if ($settings['activationMode'] === $Handler::ACTIVATION_MODE_MANUAL) {
            return;
        }

        // login
        $secHash = QUI::getUsers()->getSecHash();

        $User->setAttributes(array(
            $Handler::USER_ATTR_ACTIVATION_LOGIN_EXECUTED => true
        ));

        $User->save(QUI::getUsers()->getSystemUser());

        $Session = QUI::getSession();
        $Session->set('uid', $User->getId());
        $Session->set('auth', 1);
        $Session->set('secHash', $secHash);

        $useragent = '';

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $useragent = $_SERVER['HTTP_USER_AGENT'];
        }

        QUI::getDataBase()->update(
            QUI::getUsers()->table(),
            array(
                'lastvisit'  => time(),
                'user_agent' => $useragent,
                'secHash'    => $secHash
            ),
            array('id' => $User->getId())
        );
    }

    /**
     * quiqqer/quiqqer: onUserDelete
     *
     * @param \QUI\Users\User $User
     * @return void
     */
    public static function onUserDelete(User $User)
    {
        // delete Verification for user (if not yet deleted by quiqqer/verification cron)
        try {
            $Verification = Verifier::getVerificationByIdentifier($User->getId());
            Verifier::removeVerification($Verification);
        } catch (\Exception $Exception) {
            // nothing -> if Verification not found it does not have to be deleted
        }
    }

    /**
     * quiqqer/quiqqer: onPackageSetup
     *
     * @param QUI\Package\Package $Package
     * @return void
     */
    public static function onPackageSetup(QUI\Package\Package $Package)
    {
        if ($Package->getName() !== 'quiqqer/frontend-users') {
            return;
        }

        self::setAddressDefaultSettings();
    }

    /**
     * Set address fields default settings
     *
     * @return void
     */
    protected static function setAddressDefaultSettings()
    {
        $Conf          = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $addressFields = $Conf->getValue('registration', 'addressFields');

        // do not set default settings if manual settings have already been set
        if (!empty($addressFields)) {
            return;
        }

        $addressFields = array(
            'salutation' => array(
                'show'     => true,
                'required' => false
            ),
            'firstname'  => array(
                'show'     => true,
                'required' => true
            ),
            'lastname'   => array(
                'show'     => true,
                'required' => true
            ),
            'street_no'  => array(
                'show'     => true,
                'required' => true
            ),
            'zip'        => array(
                'show'     => true,
                'required' => true
            ),
            'city'       => array(
                'show'     => true,
                'required' => true
            ),
            'country'    => array(
                'show'     => true,
                'required' => true
            ),
            'company'    => array(
                'show'     => true,
                'required' => false
            ),
            'phone'      => array(
                'show'     => true,
                'required' => false
            )
        );

        $Conf->setValue('registration', 'addressFields', json_encode($addressFields));
        $Conf->save();
    }
}
