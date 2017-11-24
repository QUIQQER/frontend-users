<?php

/**
 * This file contains QUI\FrontendUsers\Utils
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\Utils\Text\XML;
use QUI\Utils\DOM;
use QUI\FrontendUsers\Controls\Profile\ControlWrapper;
use QUI\Permissions;
use Tracy\Debugger;

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

            if (file_exists($dir.'/frontend-users.xml')) {
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

        $result   = array();
        $packages = self::getFrontendUsersPackages();

        $Engine = QUI::getTemplateManager()->getEngine();

        /** @var QUI\Package\Package $Package */
        foreach ($packages as $Package) {
            $Parser = new QUI\Utils\XML\Settings();
            $Parser->setXMLPath('//quiqqer/frontend-users/profile');

            $Collection = $Parser->getCategories($Package->getDir().'/frontend-users.xml');

            foreach ($Collection as $entry) {
                $categoryName = $entry['name'];
                $items        = $entry['items']->toArray();

                if (!isset($result[$categoryName])) {
                    $result[$categoryName]['name']  = $entry['name'];
                    $result[$categoryName]['title'] = $entry['title'];
                    $result[$categoryName]['items'] = array();
                }

                foreach ($items as $item) {
                    $item['content'] = '';

                    if (empty($item['items'])
                        && empty($item['template'])
                        && empty($item['control'])) {
                        continue;
                    }

                    // template
                    if (isset($item['template'])) {
                        if (file_exists($item['template'])) {
                            $item['content'] = $Engine->fetch($item['template']);
                        }
                    }

                    // xml
                    if (isset($item['items'])) {
                        // @todo
                    }

                    $result[$categoryName]['items'][] = $item;
                }
            }
        }

        QUI\Cache\Manager::set($cache, $result);

        return $result;
    }

    /**
     * Return a setting for the profile
     *
     * @param string $category
     * @param bool|string $settings
     * @return array
     *
     * @throws Exception
     */
    public static function getProfileSetting($category, $settings = false)
    {
        if ($category) {
            $categories = [self::getProfileCategory($category)];
        } else {
            $categories = self::getProfileCategories();
        }

        foreach ($categories as $category) {
            foreach ($category['items'] as $settingEntry) {
                if ($settingEntry['name'] == $settings) {
                    return $settingEntry;
                }
            }
        }

        throw new Exception(array(
            'quiqqer/frontend-users',
            'exception.profile.setting.not.found'
        ));
    }

    /**
     * Return a setting control for the profile
     *
     * @param string $category
     * @param bool|string $settings
     * @return QUI\Controls\Control|ControlWrapper
     *
     * @throws Exception
     */
    public static function getProfileSettingControl($category, $settings = false)
    {
        $setting = self::getProfileSetting($category, $settings);
        $Control = null;

        if (isset($setting['control'])) {
            $cls = $setting['control'];

            if (class_exists($cls)) {
                $Control = new $cls();
            }
        }

        if ($Control === null) {
            $Control = new ControlWrapper($setting);
        }

        return $Control;
    }

    /**
     * Return a specific category
     *
     * @param string $category
     * @return array
     * @throws Exception
     */
    public static function getProfileCategory($category)
    {
        $categories = self::getProfileCategories();

        if (isset($categories[$category])) {
            return $categories[$category];
        }

        throw new Exception(array(
            'quiqqer/frontend-users',
            'exception.profile.category.not.found'
        ));
    }

    /**
     * Get data of all categories
     *
     * @return array
     */
    public static function getProfileBarCategories()
    {
        $categories = array();

        foreach (Utils::getProfileCategories() as $c) {
            if ($c['showinmenu']) {
                continue;
            }

            $categories[] = array(
                'title' => $c['text'],
                'name'  => $c['name'],
                'icon'  => $c['icon']
            );
        }

        return $categories;
    }

    /**
     * Checks if the given User is allowed to view a category
     *
     * @param string $category - Name of the category
     * @param string|bool $settings (optional) - category settings
     * @param QUI\Users\User $User (optional) - If omitted use \QUI::getUserBySession()
     * @return bool
     */
    public static function hasPermissionToViewCategory($category, $settings = false, $User = null)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        $permissionPrefix = 'quiqqer.frontendUsers.profile.view.';
        $permission       = $permissionPrefix.$category;

        if ($settings) {
            $permission = $permission.'.'.$settings;
        }

        return Permissions\Permission::hasPermission($permission, $User);
    }

    /**
     * Return the control from the profile category
     *
     * @param string $name
     * @return QUI\Controls\Control|ControlWrapper
     * @deprecated
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
