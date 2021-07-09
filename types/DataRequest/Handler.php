<?php

namespace QUI\GDPR\DataRequest;

use QUI;
use QUI\Interfaces\Users\User as UserInterface;

/**
 * Class Handler
 *
 * GDPR data request handler.
 */
class Handler
{
    /**
     * Get complete data request for a $User with basic data and
     *
     * @param UserInterface $User
     * @return array
     *
     * @throws QUI\Exception
     */
    public static function getDataRequestData(UserInterface $User): array
    {
        $regAuthority             = self::getRegulatoryAuthority();
        $regAuthorityAddressLines = [];

        $regAuthorityAttributes = [
            'name',
            'street',
            'city',
            'po_box',
            'email',
            'phone'
        ];

        foreach ($regAuthorityAttributes as $attribute) {
            if (!empty($regAuthority[$attribute])) {
                $regAuthorityAddressLines[] = $regAuthority[$attribute];
            }
        }

        // Short address
        $shortAddress = '';

        if (QUI::getPackageManager()->isInstalled('quiqqer/erp')) {
            $shortAddress = QUI\ERP\Defaults::getShortAddress();
        }

        $data = [
            'date'                            => $User->getLocale()->formatDate(\time()).'; '.\date('H:i'),
            'User'                            => $User,
            'dataProtectionOfficer'           => self::getDataProtectionOfficer(),
            'regulatoryAuthorityAddressLines' => $regAuthorityAddressLines,
            'providers'                       => [],
            'shortAddress'                    => $shortAddress
        ];

        foreach (self::getGdprDataProviders($User) as $Provider) {
            $userDataFields = $Provider->getUserDataFields();

            if (empty($userDataFields)) {
                continue;
            }

            $providerData = [
                'title'           => $Provider->getTitle(),
                'purpose'         => $Provider->getPurpose(),
                'recipients'      => $Provider->getRecipients(),
                'origin'          => $Provider->getOrigin(),
                'storageDuration' => $Provider->getStorageDuration(),
                'customText'      => $Provider->getCustomText(),
                'userDataFields'  => $userDataFields
            ];

            $data['providers'][] = $providerData;
        }

        return $data;
    }

    /**
     * @param UserInterface $User
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getDataRequestPdf(UserInterface $User): string
    {
        $Document = new QUI\HtmlToPdf\Document();

//        $Document->setAttribute('foldingMarks', true);
        $Document->setAttribute('disableSmartShrinking', true);
        $Document->setAttribute('headerSpacing', 10);
        $Document->setAttribute('marginTop', 50);
        $Document->setAttribute('marginBottom', 15);
        $Document->setAttribute('marginLeft', 0);
        $Document->setAttribute('marginRight', 0);
        $Document->setAttribute('showPageNumbers', true);

        $Engine = QUI::getTemplateManager()->getEngine();

        $requestData                                    = self::getDataRequestData($User);
        $requestData['regulatoryAuthorityAddressLines'] = \nl2br(\implode(
            "\n",
            $requestData['regulatoryAuthorityAddressLines']
        ));

        $Engine->assign($requestData);

        $Document->setHeaderHTML(
            $Engine->fetch(\dirname(__FILE__).'/DataRequest.Header.tpl')
        );

        $Document->setContentHTML(
            $Engine->fetch(\dirname(__FILE__).'/DataRequest.tpl')
        );

        $Document->setFooterCSS(
            $Engine->fetch(\dirname(__FILE__).'/DataRequest.Footer.css')
        );

        return $Document->createPDF();
    }

    /**
     * Get data protection officer contact data.
     *
     * @return array
     * @throws QUI\Exception
     */
    protected static function getDataProtectionOfficer(): array
    {
        $Conf = QUI::getPackage('quiqqer/gdpr')->getConfig();
        return $Conf->getSection('data_protection_officer');

    }

    /**
     * Get regulatory authority contact data.
     *
     * @return array
     * @throws QUI\Exception
     */
    protected static function getRegulatoryAuthority(): array
    {
        $Conf = QUI::getPackage('quiqqer/gdpr')->getConfig();
        return $Conf->getSection('data_protection_officer');
    }

    /**
     * Get all available GDPR Data provider classes
     *
     * @param UserInterface $User
     * @return DataProviderInterface[] - Provider classes
     */
    protected static function getGdprDataProviders(UserInterface $User): array
    {
        $packages  = QUI::getPackageManager()->getInstalled();
        $providers = [];

        foreach ($packages as $installedPackage) {
            try {
                $Package = QUI::getPackage($installedPackage['name']);

                if (!$Package->isQuiqqerPackage()) {
                    continue;
                }

                $packageProvider = $Package->getProvider();

                if (empty($packageProvider['gdprDataProvider'])) {
                    continue;
                }

                foreach ($packageProvider['gdprDataProvider'] as $class) {
                    if (!\class_exists($class)) {
                        continue;
                    }

                    if (!\is_a($class, DataProviderInterface::class, true)) {
                        continue;
                    }

                    /** @var DataProviderInterface $class */
                    $providers[] = new $class($User);
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $providers;
    }
}
