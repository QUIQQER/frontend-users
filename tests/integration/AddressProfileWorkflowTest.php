<?php

namespace QUI\FrontendUsers\Tests\Integration;

use QUI;
use QUI\FrontendUsers\Controls\Address\Address;
use QUI\FrontendUsers\Controls\Profile\Address as ProfileAddress;
use QUI\FrontendUsers\Controls\Profile\UserData;
use QUI\FrontendUsers\Handler;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use QUI\FrontendUsers\Utils;

class AddressProfileWorkflowTest extends DatabaseTestCase
{
    public function testAddressManagerCreatesEditsRendersAndDeletesAddress(): void
    {
        $this->setAddressFieldConfiguration();
        $User = $this->createUser(true);
        self::replaceSessionUser($User);
        $Control = new Address();
        $beforeAddresses = $User->getAddressList();
        $before = count($beforeAddresses);
        $beforeUuids = array_map(
            static fn(QUI\Users\Address $Address): string => $Address->getUUID(),
            $beforeAddresses
        );
        $_REQUEST = ['createSave' => 1];
        $Control->createAddress([
            'company' => 'Example Ltd',
            'salutation' => 'Mx',
            'firstname' => 'Address',
            'lastname' => 'Fixture',
            'street' => 'Main Street',
            'street_number' => '7',
            'zip' => '12345',
            'city' => 'Example City',
            'country' => 'DE',
            'phone' => '+49 30 123',
            'mobile' => '+49 170 123',
            'fax' => '+49 30 124',
            'email' => 'address-fixture@example.invalid'
        ]);
        $addresses = $User->getAddressList();
        self::assertCount($before + 1, $addresses);
        $Created = null;
        foreach ($addresses as $Address) {
            if (!in_array($Address->getUUID(), $beforeUuids, true)) {
                $Created = $Address;
                break;
            }
        }
        self::assertInstanceOf(QUI\Users\Address::class, $Created);

        $_REQUEST = [
            'addressId' => $Created->getUUID(),
            'editSave' => 1
        ];
        $Control->editAddress([
            'firstname' => 'Edited',
            'lastname' => 'Fixture',
            'street_no' => 'Changed Street 8',
            'zip' => '54321',
            'city' => 'Changed City',
            'country' => 'de',
            'phone' => '+49 30 999',
            'mobile' => '+49 170 999',
            'fax' => '+49 30 998',
            'email' => 'edited-address@example.invalid'
        ]);
        self::assertSame('Changed City', $Created->getAttribute('city'));

        foreach (
            [
            ['create' => 1],
            ['edit' => $Created->getUUID()],
            ['delete' => $Created->getUUID()]
            ] as $request
        ) {
            $_REQUEST = $request;
            self::assertNotSame('', $Control->getBody());
        }

        $Control->validate($Created);
        $_REQUEST = [
            'executeDeletion' => 1,
            'addressId' => $Created->getUUID()
        ];
        self::assertIsString($Control->getBody());
        self::assertCount($before, $User->getAddressList());
    }

    public function testAddressSettingsAndMissingFieldsCoverPhoneAndMailVariants(): void
    {
        $settings = Address::checkSettingsArray([
            'email' => ['show' => true, 'required' => false],
            'city' => ['show' => false]
        ]);
        self::assertTrue($settings['street']['required']);
        self::assertTrue($settings['street_number']['show']);
        self::assertFalse($settings['city']['show']);

        $this->setPackageConfig('profile', 'addressFields', json_encode([
            'firstname' => ['show' => true, 'required' => true],
            'mobile' => ['show' => true, 'required' => true],
            'fax' => ['show' => true, 'required' => true],
            'tel' => ['show' => true, 'required' => true],
            'email' => ['show' => true, 'required' => true]
        ]));
        $User = $this->createUser();
        $Address = $User->addAddress();
        self::assertEqualsCanonicalizing(
            ['firstname', 'mobile', 'fax', 'tel', 'email'],
            Utils::getMissingAddressFields($Address)
        );
        $Address->setAttribute('firstname', 'Complete');
        $Address->editPhone(0, '+49 30 123');
        $Address->editMobile('+49 170 123');
        $Address->editFax('+49 30 124');
        $Address->editMail(0, 'complete@example.invalid');
        self::assertSame([], Utils::getMissingAddressFields($Address));
    }

    public function testProfileAddressAndUserDataSaveToAuthenticatedUser(): void
    {
        $this->setAddressFieldConfiguration();
        $this->setPackageConfig('userProfile', 'useAddressManagement', 1);
        $this->setPackageConfig('userProfile', 'showLanguageChangeInProfile', 0);
        $this->setPackageConfig('registration', 'usernameInput', Handler::USERNAME_INPUT_REQUIRED);
        $User = $this->createUser(true);
        self::replaceSessionUser($User);
        $StandardAddress = $User->getStandardAddress();
        $StandardAddress->editPhone(0, ['type' => 'tel', 'no' => '+49 30 100']);
        $StandardAddress->editMobile('+49 170 100');
        $StandardAddress->editFax('+49 30 101');
        $StandardAddress->save(QUI::getUsers()->getSystemUser());

        QUI::getRequest()->request->replace([
            'editSave' => 1,
            'company' => 'Profile Ltd',
            'salutation' => 'Mx',
            'firstname' => 'Profile',
            'lastname' => 'Address',
            'street_no' => 'Profile Street 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'phone' => '+49 30 123',
            'mobile' => '+49 170 123',
            'fax' => '+49 30 124',
            'email' => 'profile-address@example.invalid'
        ]);
        $ProfileAddress = new ProfileAddress(['User' => $User]);
        $ProfileAddress->onSave();
        self::assertSame('Berlin', $User->getStandardAddress()->getAttribute('city'));

        QUI::getRequest()->request->replace([
            'firstname' => 'Updated',
            'lastname' => 'Person',
            'username' => $User->getUsername(),
            'birth_year' => '1990',
            'birth_month' => '05',
            'birth_day' => '17',
            'company' => 'Updated Ltd',
            'street' => 'Updated Street',
            'street_number' => '2',
            'zip' => '10999',
            'city' => 'Berlin',
            'country' => 'de',
            'tel' => '+49 30 777'
        ]);
        (new UserData())->onSave();
        self::assertSame('Updated', $User->getAttribute('firstname'));
        self::assertSame('1990-05-17', $User->getAttribute('birthday'));
        self::assertSame('Updated Street 2', $User->getStandardAddress()->getAttribute('street_no'));
    }

    public function testSimpleProfileAddressRendersConfiguredFieldValues(): void
    {
        $this->setAddressFieldConfiguration();
        $this->setPackageConfig('userProfile', 'useAddressManagement', 0);
        $User = $this->createUser(true);
        self::replaceSessionUser($User);
        $Address = $User->getStandardAddress();
        $Address->setAttributes([
            'company' => 'Simple Ltd',
            'firstname' => 'Simple',
            'lastname' => 'Profile',
            'street_no' => 'Simple Street 1',
            'zip' => '12345',
            'city' => 'Simple City',
            'country' => 'de'
        ]);
        $Address->editPhone(0, ['type' => 'tel', 'no' => '+49 30 123']);
        $Address->editMobile('+49 170 123');
        $Address->editFax('+49 30 124');
        $Address->editMail(0, 'simple-profile@example.invalid');

        self::assertNotSame('', (new ProfileAddress())->getBody());
        (new ProfileAddress())->onSave();
    }

    public function testAddressValidationRejectsEveryMissingRequiredCoreField(): void
    {
        $User = $this->createUser();
        $Control = new Address();
        $complete = [
            'firstname' => 'Valid',
            'lastname' => 'Address',
            'street_no' => 'Main Street 1',
            'zip' => '12345',
            'city' => 'Example City',
            'country' => 'de'
        ];

        foreach (array_keys($complete) as $missingField) {
            $Address = $User->addAddress();
            $values = $complete;
            $values[$missingField] = '';
            $Address->setAttributes($values);
            try {
                $Control->validate($Address);
                self::fail('A missing required address field must be rejected.');
            } catch (QUI\ERP\Order\Exception $Exception) {
                self::assertNotSame('', $Exception->getMessage());
            }
        }

        $Address = $User->addAddress();
        $Address->setAttributes(array_merge($complete, [
            'street_no' => '',
            'street' => 'Fallback Street',
            'street_number' => '9'
        ]));
        $Control->validate($Address);
        self::addToAssertionCount(1);
    }

    private function setAddressFieldConfiguration(): void
    {
        $fields = [];
        foreach (
            [
            'company',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country',
            'phone',
            'mobile',
            'fax',
            'email'
            ] as $field
        ) {
            $fields[$field] = ['show' => true, 'required' => false];
        }

        $this->setPackageConfig('profile', 'addressFields', json_encode($fields));
    }
}
