<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Address\Address
 */

namespace QUI\FrontendUsers\Controls\Address;

use QUI;

/**
 * Class Address
 * - Tab / Panel for the address
 *
 * @package QUI\ERP\Order\Controls
 */
class Address extends QUI\Control
{
    /**
     * Address constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Address.css');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null): string
    {
        return 'Address';
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return 'fa-address-card';
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        /* @var $User QUI\Users\User */
        $User   = QUI::getUserBySession();
        $Engine = QUI::getTemplateManager()->getEngine();

        if (isset($_REQUEST['create'])) {
            return $this->getBodyForCreate();
        }

        if (isset($_REQUEST['edit'])) {
            try {
                return $this->getBodyForEdit();
            } catch (QUI\Exception $Exception) {
            }
        }

        if (isset($_REQUEST['createSave'])) {
            try {
                $this->createAddress();
            } catch (QUI\Exception $Exception) {
            }
        }

        if (isset($_REQUEST['editSave'])) {
            try {
                $this->editAddress();
            } catch (QUI\Exception $Exception) {
            }
        }

        if (isset($_REQUEST['delete'])) {
            try {
                return $this->getBodyForDelete();
            } catch (QUI\Exception $Exception) {
            }
        }

        if (isset($_REQUEST['executeDeletion'])) {
            try {
                $this->delete();
            } catch (QUI\Exception $Exception) {
            }
        }

        $UserAddress = null;

        try {
            $UserAddress = $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
        }

        $Engine->assign([
            'this'        => $this,
            'User'        => $User,
            'UserAddress' => $UserAddress,
            'addresses'   => $User->getAddressList()
        ]);

        return $Engine->fetch(dirname(__FILE__).'/Address.html');
    }

    /**
     * Return the body for a address edit
     *
     * @return string
     * @throws QUI\Exception
     */
    protected function getBodyForEdit(): string
    {
        $User    = QUI::getUserBySession();
        $Engine  = QUI::getTemplateManager()->getEngine();
        $Address = $User->getAddress((int)$_REQUEST['edit']);

        try {
            $Conf     = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getValue('profile', 'addressFields');

            if (!empty($settings)) {
                $settings = \json_decode($settings, true);
            } else {
                $settings = [];
            }

            $Engine->assign('settings', $this->checkSettingsArray($settings));
        } catch (QUI\Exception $Exception) {
            $Engine->assign('settings', $this->checkSettingsArray([]));
        }

        $Engine->assign([
            'this'      => $this,
            'Address'   => $Address,
            'User'      => $User,
            'phone'     => $Address->getPhone(),
            'fax'       => $Address->getFax(),
            'mobile'    => $Address->getMobile(),
            'countries' => QUI\Countries\Manager::getList()
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/Address.Edit.html');
    }

    /**
     * @param array $settings
     * @return mixed
     */
    public static function checkSettingsArray(array $settings)
    {
        if (!\is_array($settings)) {
            $settings = [];
        }

        $fields = [
            'company',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country',
            'company',
            'phone',
            'mobile',
            'fax'
        ];

        foreach ($fields as $field) {
            if (!isset($settings[$field])) {
                $settings[$field] = [
                    'show'     => true,
                    'required' => true
                ];

                continue;
            }

            if (!isset($settings[$field]['required'])) {
                $settings[$field]['required'] = true;
            }

            if (!isset($settings[$field]['show'])) {
                $settings[$field]['show'] = true;
            }
        }

        if ($settings['street_no']['required']) {
            $settings['street']['required']        = true;
            $settings['street_number']['required'] = true;
        }

        if ($settings['street_no']['show']) {
            $settings['street']['show']        = true;
            $settings['street_number']['show'] = true;
        }

        return $settings;
    }

    /**
     * Return the body for a address deletion
     *
     * @return string
     * @throws QUI\Exception
     */
    protected function getBodyForDelete(): string
    {
        $User    = QUI::getUserBySession();
        $Engine  = QUI::getTemplateManager()->getEngine();
        $Address = $User->getAddress((int)$_REQUEST['delete']);

        $Engine->assign([
            'this'    => $this,
            'Address' => $Address,
            'User'    => $User
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/Address.Delete.html');
    }

    /**
     * Return the body for a address creation
     *
     * @return string
     * @throws QUI\Exception
     */
    protected function getBodyForCreate(): string
    {
        $User   = QUI::getUserBySession();
        $Engine = QUI::getTemplateManager()->getEngine();

        $currentCountry = '';
        $Country        = $User->getCountry();

        if ($Country) {
            $currentCountry = $Country->getCode();
        }

        try {
            $Conf     = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getValue('profile', 'addressFields');

            if (!empty($settings)) {
                $settings = \json_decode($settings, true);
            } else {
                $settings = [];
            }

            $Engine->assign('settings', $this->checkSettingsArray($settings));
        } catch (QUI\Exception $Exception) {
            $Engine->assign('settings', $this->checkSettingsArray([]));
        }

        $Engine->assign([
            'this'           => $this,
            'currentCountry' => $currentCountry,
            'countries'      => QUI\Countries\Manager::getList(),
            'User'           => $User
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/Address.Create.html');
    }

    /**
     * Create a new address for the user
     *
     * @param array $data - address data
     *
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function createAddress(array $data = [])
    {
        if (!isset($_REQUEST['createSave'])) {
            return;
        }

        if (empty($data)) {
            $data = $_REQUEST;
        }

        /* @var $User QUI\Users\User */
        $User    = QUI::getUserBySession();
        $Address = $User->addAddress();

        $fields = [
            'company',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country'
        ];

        if (!empty($data['street']) || !empty($data['street_number'])) {
            $street = '';

            if (!empty($data['street'])) {
                $street .= \trim($data['street']);
            }

            if (!empty($data['street_number'])) {
                $street .= ' '.\trim($data['street']);
            }

            $data['street_no'] = \trim($street);
        }

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $Address->setAttribute($field, $data[$field]);
            }
        }

        if (isset($data['phone'])) {
            $Address->editPhone(0, $data['phone']);
        }

        if (isset($data['fax'])) {
            $Address->editFax($data['fax']);
        }

        if (isset($data['mobile'])) {
            $Address->editMobile($data['mobile']);
        }

        // check required fields
        $missing = QUI\FrontendUsers\Utils::getMissingAddressFields($Address);

        if (\count($missing)) {
            $Address->delete();

            throw new QUI\Exception([
                'quiqqer/frontend-users',
                'exception.controls.profile.address.required_fields_empty'
            ]);
        }

        $Address->save();
    }

    /**
     * Edit an address
     *
     * @param array $data - address data
     *
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function editAddress(array $data = [])
    {
        if (!isset($_REQUEST['addressId']) || !isset($_REQUEST['editSave'])) {
            return;
        }

        if (empty($data)) {
            $data = $_REQUEST;
        }

        $User    = QUI::getUserBySession();
        $Address = $User->getAddress($_REQUEST['addressId']);

        if (!empty($data['street']) || !empty($data['street_number'])) {
            $street = '';

            if (!empty($data['street'])) {
                $street .= \trim($data['street']);
            }

            if (!empty($data['street_number'])) {
                $street .= ' '.\trim($data['street']);
            }

            $data['street_no'] = \trim($street);
        }

        $fields = [
            'company',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country'
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $Address->setAttribute($field, $data[$field]);
            }
        }

        if (isset($data['phone'])) {
            $Address->editPhone(0, $data['phone']);
        }

        if (isset($data['fax'])) {
            $Address->editFax($data['fax']);
        }

        if (isset($data['mobile'])) {
            $Address->editMobile($data['mobile']);
        }

        $missing = QUI\FrontendUsers\Utils::getMissingAddressFields($Address);

        if (\count($missing)) {
            throw new QUI\Exception([
                'quiqqer/frontend-users',
                'exception.controls.profile.address.required_fields_empty'
            ]);
        }

        $Address->save();
    }

    /**
     * Delete an address
     *
     * @throws QUI\Exception
     */
    protected function delete()
    {
        if (!isset($_REQUEST['addressId']) || !isset($_REQUEST['executeDeletion'])) {
            return;
        }

        $User    = QUI::getUserBySession();
        $Address = $User->getAddress($_REQUEST['addressId']);
        $Address->delete();
    }

    /**
     * Validate if the order has an invoice address
     *
     * @param QUI\Users\Address $Address
     * @throws QUI\ERP\Order\Exception
     */
    public function validate(QUI\Users\Address $Address)
    {
        $exception = [
            'quiqqer/order',
            'exception.missing.address.field'
        ];

        $firstName = $Address->getAttribute('firstname');
        $lastName  = $Address->getAttribute('lastname');
        $zip       = $Address->getAttribute('zip');
        $city      = $Address->getAttribute('city');
        $country   = $Address->getAttribute('country');

        $street_no = $Address->getAttribute('street_no');

        if (empty($street_no)) {
            $street_no = '';

            if (!empty($Address->getAttribute('street'))) {
                $street_no .= $Address->getAttribute('street');
            }

            if (!empty($Address->getAttribute('street_number'))) {
                $street_no .= ' '.$Address->getAttribute('street_number');
            }

            $street_no = \trim($street_no);
        }

        if (empty($firstName)) {
            throw new QUI\ERP\Order\Exception($exception);
        }

        if (empty($lastName)) {
            throw new QUI\ERP\Order\Exception($exception);
        }

        if (empty($street_no)) {
            throw new QUI\ERP\Order\Exception($exception);
        }

        if (empty($zip)) {
            throw new QUI\ERP\Order\Exception($exception);
        }

        if (empty($city)) {
            throw new QUI\ERP\Order\Exception($exception);
        }

        if (empty($country)) {
            throw new QUI\ERP\Order\Exception($exception);
        }
    }
}
