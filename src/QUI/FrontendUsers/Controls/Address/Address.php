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
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Address.css');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Address';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa-address-card';
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody()
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
        $addresses   = array();

        try {
            $UserAddress = $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
        }

        try {
            $addresses = $User->getAddressList();
        } catch (QUI\Exception $Exception) {
        }

        $Engine->assign(array(
            'this'        => $this,
            'User'        => $User,
            'UserAddress' => $UserAddress,
            'addresses'   => $addresses
        ));

        return $Engine->fetch(dirname(__FILE__).'/Address.html');
    }

    /**
     * Return the body for a address edit
     *
     * @return string
     * @throws QUI\Exception
     */
    protected function getBodyForEdit()
    {
        $User    = QUI::getUserBySession();
        $Engine  = QUI::getTemplateManager()->getEngine();
        $Address = $User->getAddress((int)$_REQUEST['edit']);

        $Engine->assign(array(
            'this'      => $this,
            'Address'   => $Address,
            'countries' => QUI\Countries\Manager::getList()
        ));

        return $Engine->fetch(dirname(__FILE__).'/Address.Edit.html');
    }

    /**
     * Return the body for a address deletion
     *
     * @return string
     * @throws QUI\Exception
     */
    protected function getBodyForDelete()
    {
        $User    = QUI::getUserBySession();
        $Engine  = QUI::getTemplateManager()->getEngine();
        $Address = $User->getAddress((int)$_REQUEST['delete']);

        $Engine->assign(array(
            'this'    => $this,
            'Address' => $Address
        ));

        return $Engine->fetch(dirname(__FILE__).'/Address.Delete.html');
    }

    /**
     * Return the body for a address creation
     *
     * @return string
     * @throws QUI\Exception
     */
    protected function getBodyForCreate()
    {
        $User   = QUI::getUserBySession();
        $Engine = QUI::getTemplateManager()->getEngine();

        $currentCountry = '';
        $Country        = $User->getCountry();

        if ($Country) {
            $currentCountry = $Country->getCode();
        }

        $Engine->assign(array(
            'this'           => $this,
            'currentCountry' => $currentCountry,
            'countries'      => QUI\Countries\Manager::getList()
        ));

        return $Engine->fetch(dirname(__FILE__).'/Address.Create.html');
    }

    /**
     * Create a new address for the user
     *
     * @param array $data - address data
     */
    public function createAddress($data = array())
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

        $fields = array(
            'company',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country'
        );

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $Address->setAttribute($field, $data[$field]);
            }
        }

        $Address->save();
    }

    /**
     * Edit an address
     *
     * @param array $data - address data
     */
    public function editAddress($data = array())
    {
        if (!isset($_REQUEST['addressId']) || !isset($_REQUEST['editSave'])) {
            return;
        }

        if (empty($data)) {
            $data = $_REQUEST;
        }

        $User    = QUI::getUserBySession();
        $Address = $User->getAddress($_REQUEST['addressId']);

        $fields = array(
            'company',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country'
        );

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $Address->setAttribute($field, $data[$field]);
            }
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
        $exception = array(
            'quiqqer/order',
            'exception.missing.address.field'
        );

        $firstName = $Address->getAttribute('firstname');
        $lastName  = $Address->getAttribute('lastname');
        $street_no = $Address->getAttribute('street_no');
        $zip       = $Address->getAttribute('zip');
        $city      = $Address->getAttribute('city');
        $country   = $Address->getAttribute('country');

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
