<?php

/**
 * Start registration process
 *
 * @param string $registrar - Registrar name
 * @param array $data - Registrar attributes
 * @param bool $isSignUpRegistration (optional) - Request stems from RegistrationSignUp control
 * @return array - Registrar status HTML and user activation status
 *
 * @throws QUI\Exception
 */

use QUI\FrontendUsers\Handler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_register',
    function ($registrar, $data, $registrars, $isSignUpRegistration = false) {
        if (!empty($registrars)) {
            $registrars = json_decode($registrars, true);
        } else {
            $registrars = [];
        }

        $Registration = new QUI\FrontendUsers\Controls\Registration([
            'async' => true,
            'registrars' => $registrars,
            'addressValidation' => !empty($isSignUpRegistration),
            'ignoreAlreadyRegistered' => true
        ]);

        $_POST = array_merge($_POST, json_decode($data, true));

        $_POST['registration'] = 1;
        $_POST['registrar'] = $registrar;

        $Registrar = Handler::getInstance()->getRegistrarByHash($registrar);

        try {
            $status = $Registration->create();

            if ($Registration->getRegisteredUser()) {
                try {
                    QUI::getAjax()->triggerGlobalJavaScriptCallback(
                        'quiqqerFrontendUsersUserRegisterCallback',
                        [
                            'userId' => $Registration->getRegisteredUser()->getUUID(),
                            'registrarHash' => $registrar,
                            'registrarType' => $Registrar ? $Registrar->getType() : ''
                        ]
                    );
                } catch (\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            throw new QUI\Exception([
                'quiqqer/frontend-users',
                'exception.ajax.frontend_register.general_error'
            ]);
        }

        // do not show user edit messages
        QUI::getMessagesHandler()->clear();

        $User = $Registration->getRegisteredUser();

        return [
            'html' => $status,
            'loggedIn' => QUI::getUsers()->isAuth($User),
            'userActivated' => $User && $User->isActive(),
            'userId' => $User ? $User->getUUID() : false,
            'registrarHash' => $registrar,
            'registrarType' => $Registrar ? $Registrar->getType() : ''
        ];
    },
    ['registrar', 'data', 'registrars', 'isSignUpRegistration']
);
