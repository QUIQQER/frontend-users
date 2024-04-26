<?php

namespace QUI\FrontendUsers\Rest\Routes;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface as SlimResponse;
use Psr\Http\Message\ServerRequestInterface as SlimRequest;
use QUI;
use QUI\FrontendUsers\ActivationVerification;
use QUI\FrontendUsers\Exception;
use QUI\Verification\Verifier;

use function boolval;
use function explode;
use function json_encode;
use function time;

class PostRegister
{
    /**
     * To be called by the REST Server (Slim)
     *
     * @param SlimRequest $Request
     * @param SlimResponse $Response
     * @param array $args
     *
     * @return SlimResponse
     */
    public static function call(SlimRequest $Request, SlimResponse $Response, array $args): SlimResponse
    {
        try {
            $RegistrationData = QUI\FrontendUsers\Rest\RegistrationData::buildFromRequest($Request);
            static::registerUser($RegistrationData);
        } catch (Exception $Exception) {
            return new Response(
                400,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'message' => $Exception->getMessage()
                ])
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'message' => $Exception->getMessage()
                ])
            );
        }

        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'message' => 'OK'
            ])
        );
    }

    /**
     * Creates a new User from the given RegistrationData
     *
     * @param QUI\FrontendUsers\Rest\RegistrationData $RegistrationData
     *
     * @return QUI\Interfaces\Users\User
     *
     * @throws QUI\Exception
     * @throws QUI\FrontendUsers\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Users\Exception
     */
    protected static function registerUser(
        QUI\FrontendUsers\Rest\RegistrationData $RegistrationData
    ): QUI\Interfaces\Users\User {
        $RegistrationData->validate();

        $SystemUser = QUI::getUsers()->getSystemUser();

        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();

        $NewUser = QUI::getUsers()->createChild($RegistrationData->getAttribute('username'), $SystemUser);

        // Add the given data to the User
        static::addRegistrationDataToUser($NewUser, $RegistrationData);

        // add user to default groups
        $defaultGroupIds = explode(",", $registrationSettings['defaultGroups']);

        foreach ($defaultGroupIds as $groupId) {
            $NewUser->addToGroup($groupId);
        }

        // determine if the user has to set a new password on first login
        if ($registrationSettings['forcePasswordReset']) {
            $NewUser->setAttribute('quiqqer.set.new.password', true);
        }

        $projectName = $RegistrationData->getAttribute('project_name');

        if ($projectName) {
            $Project = QUI::getProject($projectName, $RegistrationData->getAttribute('project_language'));
        } else {
            $Project = QUI::getProjectManager()->getStandard();
        }

        $RegistrarHandler->sendRegistrationNotice($NewUser, $Project);

        $NewUser->save($SystemUser);

        $password = $RegistrationData->getAttribute('password');

        if (!$password) {
            $password = QUI\Security\Password::generateRandom();
        }

        $NewUser->setPassword($password, $SystemUser);

        static::sendActivationMail($NewUser, $Project);

        QUI::getEvents()->fireEvent('quiqqerFrontendUsersUserRestRegister', [$NewUser]);

        return $NewUser;
    }

    /**
     * Writes the data from the given RegistrationData object to the given User
     *
     * @param QUI\Interfaces\Users\User $User
     * @param QUI\FrontendUsers\Rest\RegistrationData $RegistrationData
     *
     * @return void
     *
     * @throws QUI\Exception
     */
    protected static function addRegistrationDataToUser(
        QUI\Interfaces\Users\User $User,
        QUI\FrontendUsers\Rest\RegistrationData $RegistrationData
    ): void {
        $SystemUser = QUI::getUsers()->getSystemUser();

        $firstname = $RegistrationData->getAttribute('firstname');
        if ($firstname) {
            $User->setAttribute('firstname', $firstname);
        }

        $lastname = $RegistrationData->getAttribute('lastname');
        if (!empty($lastname)) {
            $User->setAttribute('lastname', $lastname);
        }

        // set e-mail address
        $User->setAttribute('email', $RegistrationData->getAttribute('email'));

        $registrationSettings = QUI\FrontendUsers\Handler::getInstance()->getRegistrationSettings();

        $useAddress = boolval($registrationSettings['addressInput']);

        // set address data
        if ($useAddress) {
            $UserAddress = $User->addAddress([
                'salutation' => $RegistrationData->getAttribute('salutation'),
                'firstname' => $RegistrationData->getAttribute('firstname'),
                'lastname' => $RegistrationData->getAttribute('lastname'),
                'mail' => $RegistrationData->getAttribute('email'),
                'company' => $RegistrationData->getAttribute('company'),
                'street_no' => $RegistrationData->getAttribute('street_no'),
                'zip' => $RegistrationData->getAttribute('zip'),
                'city' => $RegistrationData->getAttribute('city'),
                'country' => mb_strtolower($RegistrationData->getAttribute('country'))
            ], $SystemUser);

            $User->setAttributes([
                'firstname' => $RegistrationData->getAttribute('firstname'),
                'lastname' => $RegistrationData->getAttribute('lastname'),
                'address' => $UserAddress->getId()    // set as main address
            ]);

            $tel = $RegistrationData->getAttribute('phone');
            $mobile = $RegistrationData->getAttribute('mobile');
            $fax = $RegistrationData->getAttribute('fax');

            if (!empty($tel)) {
                $UserAddress->addPhone([
                    'type' => 'tel',
                    'no' => $tel
                ]);
            }

            if (!empty($mobile)) {
                $UserAddress->addPhone([
                    'type' => 'mobile',
                    'no' => $mobile
                ]);
            }

            if (!empty($fax)) {
                $UserAddress->addPhone([
                    'type' => 'fax',
                    'no' => $fax
                ]);
            }

            $UserAddress->save($SystemUser);
        }

        $User->save($SystemUser);
    }

    protected static function sendActivationMail(
        QUI\Interfaces\Users\User $User,
        QUI\Projects\Project $Project
    ): bool {
        // TODO: Verification uses Project from QUI::getRewrite instead of the parameter, therefore the default project is always used (see quiqqer/verification#5)
        $ActivationVerification = new ActivationVerification($User->getId(), [
            'project' => $Project->getName(),
            'projectLang' => $Project->getLang()
        ]);

        $activationLink = Verifier::startVerification($ActivationVerification, true);

        $L = QUI::getLocale();
        $lg = 'quiqqer/frontend-users';
        $tplDir = QUI::getPackage('quiqqer/frontend-users')->getDir() . 'templates/';
        $host = $Project->getVHost();

        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();

        try {
            $RegistrarHandler->sendMail(
                [
                    'subject' => $L->get($lg, 'mail.registration_activation.subject', [
                        'host' => $host
                    ])
                ],
                [
                    $User->getAttribute('email')
                ],
                $tplDir . 'mail.registration_activation.html',
                [
                    'body' => $L->get($lg, 'mail.registration_activation.body', [
                        'host' => $host,
                        'userId' => $User->getId(),
                        'username' => $User->getUsername(),
                        'userFirstName' => $User->getAttribute('firstname') ?: '',
                        'userLastName' => $User->getAttribute('lastname') ?: '',
                        'email' => $User->getAttribute('email'),
                        'date' => $L->formatDate(time()),
                        'activationLink' => $activationLink
                    ])
                ]
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: sendActivationMail -> Send mail failed'
            );

            QUI\System\Log::writeException($Exception);

            return false;
        }

        return true;
    }
}
