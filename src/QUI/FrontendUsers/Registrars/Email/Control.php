<?php

/**
 * This file contains QUI\FrontendUsers\Registrars\Email\Registrar
 */

namespace QUI\FrontendUsers\Registrars\Email;

use QUI;
use QUI\Countries\Controls\Select as CountrySelect;

/**
 * Class EMail
 *
 * @package QUI\FrontendUsers\Registrars
 */
class Control extends QUI\Control
{
    /**
     * Control constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        $this->setAttributes(array(
            'invalidFields' => array(),
            'fields'        => $_POST
        ));

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Control.css');
        $this->addCSSClass('quiqqer-registration');
        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine               = QUI::getTemplateManager()->getEngine();
        $RegistrarHandler     = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();
        $addressTemplate      = false;
        $showAddress          = false;
        $usernameSetting      = $registrationSettings['usernameInput'];

        $Engine->assign(array(
            'invalidFields' => $this->getAttribute('invalidFields'),
            'fields'        => $this->getAttribute('fields')
        ));

        // check if email is username
        if ($registrationSettings['usernameInput'] === $RegistrarHandler::USERNAME_INPUT_NONE) {
            $this->setJavaScriptControlOption('emailIsUsername', true);
        }

        // address input
        if (boolval($registrationSettings['addressInput'])) {
            $addressFields = $RegistrarHandler->getAddressFieldSettings();

            $Engine->assign('addressFields', $addressFields);

            if ($addressFields['country']['show']) {
                $Engine->assign('CountrySelect', new CountrySelect(array(
                    'selected' => mb_strtoupper(QUI::getRewrite()->getProject()->getLang()),
                    'required' => $addressFields['country']['required'],
                    'class'    => 'quiqqer-registration-field-element',
                    'name'     => 'country'
                )));
            }

            $addressTemplate = $Engine->fetch(dirname(__FILE__) . '/Registration.Address.html');

            foreach ($addressFields as $field) {
                if ($field['required']) {
                    $showAddress = true;
                    break;
                }
            }
        }

        $useCaptcha = boolval($registrationSettings['useCaptcha']);

        $this->setJavaScriptControlOption('usecaptcha', $useCaptcha);

        $Engine->assign(array(
            'addressTemplate' => $addressTemplate,
            'showAddress'     => $showAddress,
            'usernameSetting' => $usernameSetting,
            'passwordInput'   => $registrationSettings['passwordInput'],
            'Captcha'         => new QUI\Captcha\Controls\CaptchaDisplay(),
            'useCaptcha'      => $useCaptcha,
            'jsRequired'      => QUI\Captcha\Handler::requiresJavaScript()
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Control.html');
    }
}
