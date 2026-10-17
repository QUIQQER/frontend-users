<?php

namespace QUI\FrontendUsers\Rest\Routes;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface as SlimResponse;
use Psr\Http\Message\ServerRequestInterface as SlimRequest;
use QUI;
use QUI\FrontendUsers\Exception;
use QUI\FrontendUsers\Registrars\Email\Registrar;
use QUI\FrontendUsers\RegistrationPolicy;
use QUI\FrontendUsers\RegistrationUtils;

use function boolval;
use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;

class PostRegister
{
    /**
     * To be called by the REST Server (Slim)
     *
     * @param SlimRequest $Request
     * @param SlimResponse $Response
     * @param array<string, mixed> $args
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
                json_encode(
                    ['message' => $Exception->getMessage()],
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode(
                    ['message' => $Exception->getMessage()],
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                )
            );
        }

        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(
                ['message' => 'OK'],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
            )
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
        QUI\FrontendUsers\RegistrationThrottle::reserve(
            $RegistrationData->getAttribute('email'),
            $RegistrationData->getAttribute('username')
        );
        $RegistrationData->validate();

        $NewUser = QUI\FrontendUsers\RegistrationTransaction::run(
            (string)$RegistrationData->getAttribute('username'),
            (string)$RegistrationData->getAttribute('email'),
            static function () use ($RegistrationData): QUI\Interfaces\Users\User {
                $SystemUser = QUI::getUsers()->getSystemUser();

                $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
                $registrationSettings = $RegistrarHandler->getRegistrationSettings();

                $projectName = $RegistrationData->getAttribute('project_name');

                if ($projectName) {
                    $Project = QUI::getProject(
                        $projectName,
                        $RegistrationData->getAttribute('project_language')
                    );
                } else {
                    $Project = QUI::getProjectManager()->getStandard();
                }

                $Registrar = new Registrar();
                $Registrar->setProject($Project);
                $Policy = new RegistrationPolicy();
                $NewUser = QUI::getUsers()->createChild($RegistrationData->getAttribute('username'), $SystemUser);
                $Policy->setUserAttributes($NewUser, $Registrar, $Project);

                // Add the given data to the User
                static::addRegistrationDataToUser($NewUser, $RegistrationData);

                // add user to default groups
                foreach (RegistrationUtils::parseDefaultGroupIds($registrationSettings['defaultGroups']) as $groupId) {
                    $NewUser->addToGroup($groupId);
                }

                // determine if the user has to set a new password on first login
                if ($registrationSettings['forcePasswordReset']) {
                    $NewUser->setAttribute('quiqqer.set.new.password', true);
                }

                $RegistrarHandler->sendRegistrationNotice($NewUser, $Project);

                $NewUser->save($SystemUser);

                $password = $RegistrationData->getAttribute('password');

                if (!$password) {
                    $password = QUI\Security\Password::generateRandom();
                }

                $NewUser->setPassword($password, $SystemUser);

                $Policy->activate(
                    $NewUser,
                    $Registrar,
                    $Project,
                    static fn(): bool => static::sendActivationMail($NewUser, $Project)
                );

                return $NewUser;
            }
        );

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

            if ($UserAddress === null) {
                throw new QUI\Exception('The required user address is unavailable.');
            }

            $User->setAttributes([
                'firstname' => $RegistrationData->getAttribute('firstname'),
                'lastname' => $RegistrationData->getAttribute('lastname'),
                'address' => $UserAddress->getUUID()    // set as main address
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

    /**
     * @throws QUI\Exception
     * @throws \Exception
     */
    protected static function sendActivationMail(
        QUI\Interfaces\Users\User $User,
        QUI\Projects\Project $Project
    ): bool {
        $Registrar = new Registrar();
        $Registrar->setProject($Project);

        return QUI\FrontendUsers\Handler::getInstance()->sendActivationMail($User, $Registrar);
    }
}
