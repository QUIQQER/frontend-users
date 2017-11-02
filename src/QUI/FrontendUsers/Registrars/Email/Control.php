<?php

/**
 * This file contains QUI\FrontendUsers\Registrars\Email\Registrar
 */

namespace QUI\FrontendUsers\Registrars\Email;

use QUI;

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

        if (boolval($registrationSettings['addressInput'])) {
            $addressFields = $RegistrarHandler->getAddressFieldSettings();

            $Engine->assign('addressFields', $addressFields);
            $addressTemplate = $Engine->fetch(dirname(__FILE__) . '/Registration.Address.html');

            foreach ($addressFields as $field) {
                if ($field['required']) {
                    $showAddress = true;
                    break;
                }
            }
        }

        $Engine->assign(array(
            'fields'          => $this->getAttribute('fields'),
            'invalidFields'   => $this->getAttribute('invalidFields'),
            'addressTemplate' => $addressTemplate,
            'showAddress'     => $showAddress,
            'usernameSetting' => $usernameSetting,
            'passwordInput'   => $registrationSettings['passwordInput']
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Control.html');
    }
}
