<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\Address
 */

namespace QUI\FrontendUsers\Controls\Profile;

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
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-address');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');
    }

    /**
     * @return string
     *
     * @throws QUI\Users\Exception
     * @throws QUI\Exception
     */
    public function getBody()
    {
        /** @var QUI\Users\User $User */
        $User          = QUI::getUserBySession();
        $UserAddress   = $User->getStandardAddress();
        $userPhoneList = $UserAddress->getPhoneList();
        $Engine        = QUI::getTemplateManager()->getEngine();
        $addressFields = QUI\FrontendUsers\Handler::getInstance()->getAddressFieldSettings();

        foreach ($addressFields as $field => $options) {
            $options['value'] = '';

            switch ($field) {
                case 'country':
                    $countryCode = false;

                    try {
                        $Country     = $UserAddress->getCountry();
                        $countryCode = mb_strtoupper($Country->getCode());
                    } catch (\Exception $Exception) {
                        // nothing, user has no country
                    }

                    $Engine->assign('CountrySelect', new CountrySelect(array(
                        'selected' => $countryCode,
                        'required' => $options['required'],
                        'class'    => 'quiqqer-registration-field-element',
                        'name'     => 'country'
                    )));
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

        $Engine->assign(array(
            'User'          => QUI::getUserBySession(),
            'addressFields' => $addressFields
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Address.html');
    }

    /**
     * Method is called, when on save is triggered
     *
     * @return mixed|void
     * @throws QUI\Users\Exception
     * @throws QUI\Exception
     */
    public function onSave()
    {
        $Request = QUI::getRequest()->request;
        $User    = $this->getAttribute('User');

        if (!$User) {
            $User = QUI::getUserBySession();
        }

        /** @var QUI\Users\User $User */
        $UserAddress   = $User->getStandardAddress();
        $userPhoneList = $UserAddress->getPhoneList();
        $addressFields = QUI\FrontendUsers\Handler::getInstance()->getAddressFieldSettings();

        foreach ($addressFields as $field => $options) {
            $value = Orthos::clear($Request->get($field));

            if (empty($value) && $options['required']) {
                throw new QUI\FrontendUsers\Exception(array(
                    'quiqqer/frontend-users',
                    'exception.controls.profile.address.required_fields_empty'
                ));
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
                        $UserAddress->editPhone($index, array(
                            'no'   => $value,
                            'type' => 'tel'
                        ));
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
                        $UserAddress->editPhone($index, array(
                            'no'   => $value,
                            'type' => $field
                        ));
                    }
                    break;

                default:
                    $UserAddress->setAttribute($field, $value);
            }
        }

        $UserAddress->save();
    }
}
