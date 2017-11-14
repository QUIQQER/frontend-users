<?php

/**
 * This file contains QUI\FrontendUsers\Utils
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\Utils\Text\XML;
use QUI\Utils\DOM;
use QUI\FrontendUsers\Controls\Profile\ControlWrapper;

/**
 * Class Utils
 *
 * @package QUI\FrontendUsers
 */
class Utils
{
    /**
     * Return all packages which have a frontend-users.xml
     *
     * @return array
     */
    public static function getFrontendUsersPackages()
    {
        $packages = QUI::getPackageManager()->getInstalled();
        $list     = array();

        /* @var $Package \QUI\Package\Package */
        foreach ($packages as $package) {
            try {
                $Package = QUI::getPackage($package['name']);
            } catch (QUI\Exception $Exception) {
                continue;
            }

            if (!$Package->isQuiqqerPackage()) {
                continue;
            }

            $dir = $Package->getDir();

            if (file_exists($dir . '/frontend-users.xml')) {
                $list[] = $Package;
            }
        }

        return $list;
    }

    /**
     * Return the extra profile categories from other plugins
     * search intranet.xml
     *
     * @return array
     */
    public static function getProfileCategories()
    {
        $cache = 'package/quiqqer/frontendUsers/profileCategories';

        try {
            return QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception $exception) {
        }

        $catId    = 0;
        $result   = array();
        $packages = self::getFrontendUsersPackages();

        foreach ($packages as $Package) {
            $Dom  = XML::getDomFromXml($Package->getDir() . '/frontend-users.xml');
            $Path = new \DOMXPath($Dom);

            $tabs = $Path->query("//quiqqer/frontend-users/profile/tab");

            foreach ($tabs as $Item) {
                /* @var $Item \DOMElement */
                $Text      = $Item->getElementsByTagName('text')->item(0);
                $Templates = $Item->getElementsByTagName('template');

                $name     = 'category-' . $catId;
                $template = '';

                if ($Item->getAttribute('name')) {
                    $name = $Item->getAttribute('name');
                }

                if ($Templates->length) {
                    $template = DOM::parseVar($Templates->item(0)->nodeValue);
                }

                $result[] = array(
                    'text'     => DOM::getTextFromNode($Text),
                    'name'     => $name,
                    'icon'     => DOM::parseVar($Item->getAttribute('icon')),
                    'require'  => $Item->getAttribute('require'),
                    'exec'     => $Item->getAttribute('exec'),
                    'control'  => $Item->getAttribute('control'),
                    'template' => $template
                );

                $catId++;
            }
        }

        QUI\Cache\Manager::set($cache, $result);

        return $result;
    }

    /**
     * Return a specific category
     *
     * @param $name
     * @return array
     * @throws Exception
     */
    public static function getProfileCategory($name)
    {
        $categories = self::getProfileCategories();

        foreach ($categories as $category) {
            if ($category['name'] === $name) {
                return $category;
            }
        }

        throw new Exception(array(
            'quiqqer/frontend-users',
            'exception.profile.category.not.found'
        ));
    }

    /**
     * Return the control from the profile category
     *
     * @param string $name
     * @return QUI\Controls\Control|ControlWrapper
     */
    public static function getProfileCategoryControl($name)
    {
        $category = self::getProfileCategory($name);
        $Control  = null;

        if (isset($category['control'])) {
            $cls = $category['control'];

            if (class_exists($cls)) {
                $Control = new $cls();
            }
        }

        if ($Control === null) {
            $Control = new ControlWrapper($category);
        }

        return $Control;
    }
}
