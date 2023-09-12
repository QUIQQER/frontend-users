<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\UserData
 */

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\FrontendUsers\Handler as FrontendUsersHandler;
use QUI\Utils\Security\Orthos;

use function in_array;
use function json_encode;
use function trim;

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
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-userdata');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');
        $this->addCSSFile(dirname(__FILE__) . '/UserData.css');

        $this->setJavaScriptControl('package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData');
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $action               = false;
        $emailChangeRequested = true;

        $User   = QUI::getUserBySession();
        $Engine = QUI::getTemplateManager()->getEngine();
        $Config = QUI::getPackage('quiqqer/frontend-users')->getConfig();

        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();

        if (!empty($_REQUEST['action'])) {
            $action = $_REQUEST['action'];
        }

        try {
            QUI\Verification\Verifier::getVerificationByIdentifier(
                $User->getId(),
                QUI\FrontendUsers\EmailConfirmVerification::getType(),
                true
            );
        } catch (\Exception $Exception) {
            $emailChangeRequested = false;
        }

        /* @var $User QUI\Users\User */
        try {
            $Address = $User->getStandardAddress();
        } catch (QUI\Users\Exception $Exception) {
            $Address = $User->addAddress();
        }

        $Engine->assign([
            'User'              => $User,
            'Address'           => $Address,
            'action'            => $action,
            'changeMailRequest' => $emailChangeRequested,
            'username'          => $RegistrarHandler->isUsernameInputAllowed(),
            'registrationText'  => QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'quiqqer.profile.registration.date.text',
                ['date' => QUI::getLocale()->formatDate($User->getAttribute('regdate'))]
            ),

            'showLanguageChangeInProfile' => $Config->getValue('userProfile', 'showLanguageChangeInProfile')
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/UserData.html');
    }

    /**
     * event: on save
     *
     * @throws QUI\FrontendUsers\Exception
     * @throws QUI\Exception
     */
    public function onSave()
    {
        $Request  = QUI::getRequest()->request;
        $newEmail = $Request->get('emailNew');
        $User     = QUI::getUserBySession();

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

        // language
        $Config     = QUI::getPackage('quiqqer/frontend-users')->getConfig();
        $changeLang = (int)$Config->getValue('userProfile', 'showLanguageChangeInProfile');

        if ($changeLang && $Request->has('language')) {
            $Project   = QUI::getRewrite()->getProject();
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

        if ($Request->has('birth_year')
            && $Request->has('birth_month')
            && $Request->has('birth_day')) {
            $bday .= $Request->get('birth_year');
            $bday .= '-' . $Request->get('birth_month');
            $bday .= '-' . $Request->get('birth_day');
            $Request->set('birthday', $bday);
        }

        foreach ($allowedFields as $field) {
            if ($Request->has($field)) {
                $User->setAttribute($field, $Request->get($field));
            }
        }

        $User->save();


        // update first address
        try {
            $Address     = $User->getStandardAddress();
            $addressData = [];

            if ($Request->has('firstname')) {
                $addressData['firstname'] = $Request->get('firstname');
            }

            if ($Request->has('lastname')) {
                $addressData['lastname'] = $Request->get('lastname');
            }

            if ($Request->has('company')) {
                $addressData['company'] = $Request->get('company');
            }

            if ($Request->has('street_no')) {
                $addressData['street_no'] = $Request->get('street_no');
            }

            if ($Request->has('street')) {
                $addressData['street_no'] = trim($Request->get('street')) . ' ' . trim($Request->get('street_number'));
                $addressData['street_no'] = trim($addressData['street_no']);
            }

            if ($Request->has('zip')) {
                $addressData['zip'] = $Request->get('zip');
            }

            if ($Request->has('city')) {
                $addressData['city'] = $Request->get('city');
            }

            if ($Request->has('country')) {
                $addressData['country'] = $Request->get('country');
            }

            if ($Request->has('tel')) {
                $phones  = $Address->getPhoneList();
                $updated = false;

                foreach ($phones as $k => $entry) {
                    if ($entry['type'] === 'tel') {
                        $phones[$k]['no'] = $Request->get('tel');
                        $updated          = true;
                        break;
                    }
                }

                if (!$updated) {
                    $Address->addPhone([
                        'no'   => $Request->get('tel'),
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
