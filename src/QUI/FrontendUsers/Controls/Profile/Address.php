<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\Address
 */

namespace QUI\FrontendUsers\Controls\Profile;

use Exception;
use QUI;
use QUI\Countries\Controls\Select as CountrySelect;
use QUI\Utils\Security\Orthos;

/**
 * Class Address
 *
 * Change user address
 */
class Address extends AbstractProfileControl
{
    /**
     * Address constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-address');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $Package = QUI::getPackage('quiqqer/frontend-users');
        $Config = $Package->getConfig();

        if ($Config->get('userProfile', 'useAddressManagement')) {
            $Engine = QUI::getTemplateManager()->getEngine();

            $Engine->assign([
                'User' => QUI::getUserBySession(),
                'manager' => true
            ]);

            return $Engine->fetch(dirname(__FILE__) . '/Address.html');
        }

        /** @var QUI\Users\User $User */
        $User = QUI::getUserBySession();

        try {
            $UserAddress = $User->getStandardAddress();
        } catch (QUI\Users\Exception) {
            // if no user address exist -> create one
            $SystemUser = QUI::getUsers()->getSystemUser();

            $UserAddress = $User->addAddress([
                'firstname' => $User->getAttribute('firstname'),
                'lastname' => $User->getAttribute('lastname')
            ], $SystemUser);

            $User->setAttribute('address', $UserAddress->getUUID());
            $User->save($SystemUser);
        }

        $userPhoneList = $UserAddress->getPhoneList();
        $Engine = QUI::getTemplateManager()->getEngine();
        $addressFields = QUI\FrontendUsers\Handler::getInstance()->getAddressFieldSettings();

        foreach ($addressFields as $field => $options) {
            $options['value'] = '';

            switch ($field) {
                case 'country':
                    $countryCode = false;

                    try {
                        $Country = $UserAddress->getCountry();
                        $countryCode = mb_strtoupper($Country->getCode());
                    } catch (Exception) {
                        // nothing, user has no country
                    }

                    $Engine->assign(
                        'CountrySelect',
                        new CountrySelect([
                            'selected' => $countryCode,
                            'required' => $options['required'],
                            'class' => 'quiqqer-registration-field-element',
                            'name' => 'country'
                        ])
                    );
                    break;

                case 'phone':
                    foreach ($userPhoneList as $data) {
                        if ($data['type'] === 'tel') {
                            $options['value'] = $data['no'];
                        }
                    }
                    break;

                case 'mobile':
                case 'fax':
                    foreach ($userPhoneList as $data) {
                        if ($data['type'] === $field) {
                            $options['value'] = $data['no'];
                        }
                    }
                    break;

                default:
                    $options['value'] = $UserAddress->getAttribute($field);
            }

            $addressFields[$field] = $options;
        }

        $Engine->assign([
            'User' => QUI::getUserBySession(),
            'addressFields' => $addressFields
        ]);

        return $Engine->fetch($this->getTemplateFile());
    }

    /**
     * Method is called, when on save is triggered
     *
     * @return void
     * @throws QUI\Exception
     * @throws QUI\FrontendUsers\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Users\Exception
     */
    public function onSave(): void
    {
        $Package = QUI::getPackage('quiqqer/frontend-users');
        $Config = $Package->getConfig();

        if (!$Config->get('userProfile', 'useAddressManagement')) {
            return;
        }


        $Request = QUI::getRequest()->request;
        $User = $this->getAttribute('User');

        if (!$User) {
            $User = QUI::getUserBySession();
        }

        if (QUI::getUsers()->isNobodyUser($User)) {
            return;
        }

        if ($Request->get('editSave') || $Request->get('createSave')) {
            $this->saveAddress($User);
        }

        $User->save();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/frontend-users',
                'message.user.saved.successfully'
            )
        );
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @throws QUI\Exception
     * @throws QUI\FrontendUsers\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Users\Exception
     */
    protected function saveAddress(QUI\Interfaces\Users\User $User): void
    {
        $Request = QUI::getRequest()->request;
        $User = $this->getAttribute('User');

        if (!$User) {
            $User = QUI::getUserBySession();
        }

        if (QUI::getUsers()->isNobodyUser($User)) {
            return;
        }

        /** @var QUI\Users\User $User */
        $UserAddress = $User->getStandardAddress();
        $userPhoneList = $UserAddress->getPhoneList();
        $addressFields = QUI\FrontendUsers\Handler::getInstance()->getAddressFieldSettings();

        foreach ($addressFields as $field => $options) {
            $value = Orthos::clear($Request->get($field));

            if (empty($value) && $options['required']) {
                throw new QUI\FrontendUsers\Exception([
                    'quiqqer/frontend-users',
                    'exception.controls.profile.address.required_fields_empty'
                ]);
            }

            switch ($field) {
                case 'country':
                    $UserAddress->setAttribute('country', mb_strtolower($value));
                    break;

                case 'phone':
                    $index = false;

                    foreach ($userPhoneList as $k => $data) {
                        if ($data['type'] === 'tel') {
                            $index = $k;
                            break;
                        }
                    }

                    if ($index !== false) {
                        $UserAddress->editPhone($index, [
                            'no' => $value,
                            'type' => 'tel'
                        ]);
                    }
                    break;
                case 'mobile':
                case 'fax':
                    $index = false;

                    foreach ($userPhoneList as $k => $data) {
                        if ($data['type'] === $field) {
                            $index = $k;
                            break;
                        }
                    }

                    if ($index !== false) {
                        $UserAddress->editPhone($index, [
                            'no' => $value,
                            'type' => $field
                        ]);
                    }
                    break;

                default:
                    $UserAddress->setAttribute($field, $value);
            }
        }

        $UserAddress->save();
    }
}
