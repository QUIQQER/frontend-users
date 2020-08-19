<?php

/**
 * Start registration process
 *
 * @param string $registrar - Registrar name
 * @param array $data - Registrar attributes
 * @return array - Registrar status HTML and user activation status
 *
 * @throws QUI\Exception
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_register',
    function ($registrar, $data, $registrars) {
        if (!empty($registrars)) {
            $registrars = json_decode($registrars, true);
        } else {
            $registrars = [];
        }

        $Registration = new QUI\FrontendUsers\Controls\Registration([
            'async'      => true,
            'registrars' => $registrars
        ]);

        $_POST = array_merge($_POST, json_decode($data, true));

        $_POST['registration'] = 1;
        $_POST['registrar']    = $registrar;

        $Registrar = \QUI\FrontendUsers\Handler::getInstance()->getRegistrarByHash($registrar);

        try {
            $status = $Registration->create();

            if ($Registration->getRegisteredUser()) {
                try {
                    QUI::getAjax()->triggerGlobalJavaScriptCallback(
                        'quiqqerFrontendUsersUserRegisterCallback',
                        [
                            'userId'        => $Registration->getRegisteredUser()->getId(),
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
            'html'          => $status,
            'userActivated' => $User->isActive(),
            'userId'        => $User->getId(),
            'registrarHash' => $registrar,
            'registrarType' => $Registrar ? $Registrar->getType() : ''
        ];
    },
    ['registrar', 'data', 'registrars']
);
