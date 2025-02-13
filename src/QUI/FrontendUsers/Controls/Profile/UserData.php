<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\UserData
 */

namespace QUI\FrontendUsers\Controls\Profile;

use Exception;
use QUI;
use QUI\FrontendUsers\Handler as FrontendUsersHandler;
use QUI\Utils\Security\Orthos;
use QUI\Verification\Interface\VerificationRepositoryInterface;
use QUI\Verification\VerificationRepository;

use function array_filter;
use function array_keys;
use function in_array;
use function json_decode;
use function json_encode;
use function trim;
use QUI\Verification\Enum\VerificationStatus;

/**
 * Class UserData
 *
 * Change basic User data
 */
class UserData extends AbstractProfileControl
{
    /**
     * UserData constructor.
     * @param array $attributes
     */
    public function __construct(
        array $attributes = [],
        private ?VerificationRepositoryInterface $verificationRepository = null
    ) {
        if (is_null($this->verificationRepository)) {
            $this->verificationRepository = new VerificationRepository();
        }

        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-userdata');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');

        if (!defined('QUIQQER_CONTROL_TEMPLATE_USE_BASIC') || QUIQQER_CONTROL_TEMPLATE_USE_BASIC !== true) {
            $this->addCSSFile(dirname(__FILE__) . '/UserData.css');
        }

        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData');
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $action = false;
        $emailChangeRequested = true;

        $User = QUI::getUserBySession();
        $Engine = QUI::getTemplateManager()->getEngine();
        $Config = QUI::getPackage('quiqqer/frontend-users')->getConfig();

        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();

        if (!empty($_REQUEST['action'])) {
            $action = $_REQUEST['action'];
        }

        try {
            $verification = $this->verificationRepository->findByIdentifier(
                'confirmemail-' . $User->getUUID()
            );

            if (is_null($verification) || $verification->status !== VerificationStatus::PENDING) {
                $emailChangeRequested = false;
            }
        } catch (Exception) {
            $emailChangeRequested = false;
        }

        /* @var $User QUI\Users\User */
        try {
            $Address = $User->getStandardAddress();
        } catch (QUI\Users\Exception) {
            $Address = $User->addAddress();
        }

        $Engine->assign([
            'User' => $User,
            'Address' => $Address,
            'action' => $action,
            'changeMailRequest' => $emailChangeRequested,
            'username' => $RegistrarHandler->isUsernameInputAllowed(),
            'registrationText' => QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'quiqqer.profile.registration.date.text',
                ['date' => QUI::getLocale()->formatDate($User->getAttribute('regdate'))]
            ),

            'showLanguageChangeInProfile' => $Config->getValue('userProfile', 'showLanguageChangeInProfile')
        ]);

        return $Engine->fetch($this->getTemplateFile());
    }

    /**
     * event: on save
     *
     * @throws QUI\FrontendUsers\Exception
     * @throws QUI\Exception
     */
    public function onSave(): void
    {
        $Request = QUI::getRequest()->request;
        $newEmail = $Request->get('emailNew');
        $User = QUI::getUserBySession();

        if (QUI::getUsers()->isNobodyUser($User)) {
            return;
        }

        /* @var $User QUI\Users\User */

        if (!empty($newEmail)) {
            if (!Orthos::checkMailSyntax($newEmail)) {
                throw new QUI\FrontendUsers\Exception([
                    'quiqqer/frontend-users',
                    'exception.controls.profile.userdata.invalid_new_email_address'
                ]);
            }

            if (QUI::getUsers()->emailExists($newEmail)) {
                throw new QUI\FrontendUsers\Exception([
                    'quiqqer/frontend-users',
                    'exception.controls.profile.userdata.new_email_address_already_exists'
                ]);
            }

            if ($newEmail === $User->getAttribute('email')) {
                throw new QUI\FrontendUsers\Exception([
                    'quiqqer/frontend-users',
                    'exception.controls.profile.userdata.new_email_address_no_change'
                ]);
            }

            FrontendUsersHandler::getInstance()->sendChangeEmailAddressMail(
                $User,
                $newEmail,
                QUI::getRewrite()->getProject()
            );
        }

        // require fields
        $Config = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $settings = $Config->getValue('profile', 'addressFields');

        if (!empty($settings)) {
            $settings = json_decode($settings, true);
        } else {
            $settings = [];
        }

        $required = array_filter($settings, function ($field) {
            return $field['required'];
        });

        $required = array_keys($required);

        $checkFields = function ($fieldName) use ($required, $Request) {
            // wenn kein required, kann auch geleert werden
            if ($Request->has($fieldName) && !in_array($fieldName, $required)) {
                return true;
            }

            if ($Request->get($fieldName)) {
                return true;
            }

            return false;
        };


        // language
        $changeLang = (int)$Config->getValue('userProfile', 'showLanguageChangeInProfile');

        if ($changeLang && $Request->has('language')) {
            $Project = QUI::getRewrite()->getProject();
            $languages = $Project->getLanguages();

            if (in_array($Request->get('language'), $languages)) {
                $User->setAttribute('lang', $Request->get('language'));
                $User->save();

                $User->getLang();
            }
        }

        // user data
        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();

        $allowedFields = [
            'firstname',
            'lastname',
            'birthday'
        ];

        // allow edit of username if username can be set on registration
        if ($RegistrarHandler->isUsernameInputAllowed()) {
            $allowedFields[] = 'username';
        }

        // special case: birthday
        $bday = '';

        if (
            $Request->has('birth_year')
            && $Request->has('birth_month')
            && $Request->has('birth_day')
        ) {
            $bday .= $Request->get('birth_year');
            $bday .= '-' . $Request->get('birth_month');
            $bday .= '-' . $Request->get('birth_day');
            $Request->set('birthday', $bday);
        }

        foreach ($allowedFields as $field) {
            if ($checkFields($field)) {
                $User->setAttribute($field, $Request->get($field));
            }
        }

        $User->save();


        // update first address
        try {
            $Address = $User->getStandardAddress();
            $addressData = [];

            if ($checkFields('firstname')) {
                $addressData['firstname'] = $Request->get('firstname');
            }

            if ($checkFields('lastname')) {
                $addressData['lastname'] = $Request->get('lastname');
            }

            if ($checkFields('company')) {
                $addressData['company'] = $Request->get('company');
            }

            if ($checkFields('street_no')) {
                $addressData['street_no'] = $Request->get('street_no');
            }

            // street kommt manchmal als ganzes, dann dies zulassen
            if ($Request->get('street')) {
                $addressData['street_no'] = trim($Request->get('street')) . ' ' . trim($Request->get('street_number'));
                $addressData['street_no'] = trim($addressData['street_no']);
            }

            if ($checkFields('zip')) {
                $addressData['zip'] = $Request->get('zip');
            }

            if ($checkFields('city')) {
                $addressData['city'] = $Request->get('city');
            }

            if ($checkFields('country')) {
                $addressData['country'] = $Request->get('country');
            }

            if ($checkFields('tel')) {
                $phones = $Address->getPhoneList();
                $updated = false;

                foreach ($phones as $k => $entry) {
                    if ($entry['type'] === 'tel') {
                        $phones[$k]['no'] = $Request->get('tel');
                        $updated = true;
                        break;
                    }
                }

                if (!$updated) {
                    $Address->addPhone([
                        'no' => $Request->get('tel'),
                        'type' => 'tel'
                    ]);
                } else {
                    $Address->setAttribute('phone', json_encode($phones));
                }
            }

            $Address->setAttributes($addressData);
            $Address->save();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'message.user.saved.successfully'
            )
        );
    }
}
