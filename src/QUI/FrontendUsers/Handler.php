<?php

/**
 * This file contains QUI\FrontendUsers\Handler
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\Utils\Singleton;

/**
 * Class Registration Handling
 * - Main Registration Handler
 *
 * @package QUI\FrontendUsers
 */
class Handler extends Singleton
{
    const REGISTRATION_STATUS_ERROR = 0;
    const REGISTRATION_STATUS_SUCCESS = 1;
    const REGISTRATION_STATUS_PENDING = 2;

    /**
     * @var null|RegistratorCollection
     */
    protected $Registrator = null;

    /**
     * Handler constructor.
     */
    public function __construct()
    {
        $this->Registrator = new RegistratorCollection();
    }

    /**
     * @return RegistratorCollection
     */
    public function getRegistrators()
    {
        $Registrators = new RegistratorCollection();
        $Available    = $this->getAvailableRegistrators();

        foreach ($Available as $Registrator) {
            // @todo nicht aktive raus filtern
            $Registrators->append($Registrator);
        }

        return $Registrators;
    }

    /**
     * Return all available registrator
     *
     * @return RegistratorCollection
     */
    public function getAvailableRegistrators()
    {
        if ($this->Registrator->isEmpty() !== false) {
            return $this->Registrator;
        }

        $list      = array();
        $installed = QUI::getPackageManager()->getInstalled();

        foreach ($installed as $package) {
            try {
                $Package = QUI::getPackage($package['name']);

                if (!$Package->isQuiqqerPackage()) {
                    continue;
                }

                $list = array_merge($list, $Package->getProvider('registrator'));
            } catch (QUI\Exception $exception) {
            }
        }

        foreach ($list as $provider) {
            try {
                if (!class_exists($provider)) {
                    continue;
                }

                $this->Registrator->append(new $provider());
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $this->Registrator;
    }
}
