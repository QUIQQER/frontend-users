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
     * Return all extra profile categories
     * - search intranet.xml
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

        // build default control wrapper if a category has no dedicated control class
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
     *
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
     * Return all categories and settings for the profile control
     *
     * @return array
     */
    public static function getProfileCategorySettings()
    {
        $categories = Utils::getProfileCategories();

        foreach ($categories as $key => $category) {
            $items = $category['items'];

            foreach ($items as $iKey => $setting) {
                if (!isset($setting['showinprofile'])) {
                    continue;
                }

                if (!(int)$setting['showinprofile']) {
                    unset($categories[$key]['items'][$iKey]);
                }
            }

            // reindex
            $categories[$key]['items'] = array_values($categories[$key]['items']);
        }

        return $categories;
    }

    /**
     * Return all categories and settings for the profile bar control
     *
     * @return array
     */
    public static function getProfileBarCategorySettings()
    {
        $categories = Utils::getProfileCategories();

        foreach ($categories as $key => $category) {
            $items    = $category['items'];
            $newItems = array();

            foreach ($items as $iKey => $setting) {
                if (!isset($setting['showinprofilbar'])) {
                    continue;
                }

                if (!(int)$setting['showinprofilbar']) {
                    continue;
                }

                $newItems[$iKey] = $setting;
            }

            // reindex
            $categories[$key]['items'] = array_values($newItems);
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
     * Search title arrays and set the locale translations to it
     *
     * @param array $categories
     * @return array
     */
    public static function loadTranslationForCategories($categories = array())
    {
        // load the translations
        foreach ($categories as $key => $category) {
            $categories[$key]['title'] = QUI::getLocale()->get(
                $category['title'][0],
                $category['title'][1]
            );

            foreach ($category['items'] as $itemKey => $item) {
                if (!is_array($categories[$key]['items'][$itemKey]['title'])) {
                    continue;
                }

                $categories[$key]['items'][$itemKey]['title'] = QUI::getLocale()->get(
                    $categories[$key]['items'][$itemKey]['title'][0],
                    $categories[$key]['items'][$itemKey]['title'][1]
                );
            }
        }

        return $categories;
    }


    /**
     * Search title arrays and set the locale translations to it
     *
     * @param array $categories
     * @param null|QUI\Projects\Project $Project
     * @return array
     */
    public static function setUrlsToCategorySettings($categories = array(), $Project = null)
    {
        if ($Project === null) {
            $Project = QUI::getRewrite()->getProject();
        }

        $ids = $Project->getSitesIds(array(
            'where' => array(
                'type' => 'quiqqer/frontend-users:types/profile'
            ),
            'limit' => 1
        ));

        if (!isset($ids[0])) {
            $Site = $Project->firstChild();
        } else {
            $Site = $Project->get($ids[0]['id']);
        }

        $url = $Site->getUrlRewritten();

        // load the translations
        foreach ($categories as $key => $category) {
            foreach ($category['items'] as $itemKey => $item) {
                $itemUrl = $url.'/'.$category['name'];
                $itemUrl = $itemUrl.'/'.$item['name'];

                $categories[$key]['items'][$itemKey]['url'] = $itemUrl;
            }
        }

        return $categories;
    }

    /**
     * Is quiqqer/captcha installed?
     *
     * @return bool
     */
    public static function isCaptchaModuleInstalled()
    {
        try {
            QUI::getPackage('quiqqer/captcha');
        } catch (\Exception $Exception) {
            return false;
        }

        return true;
    }
}
