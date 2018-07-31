<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\Control;
use QUI\FrontendUsers\Utils;
use Tracy\Debugger;

/**
 * Class Profile
 * - Profile Settings Control
 *
 * @package QUI\FrontendUsers\Controls
 */
class Profile extends Control
{
    /**
     * Profile constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'category' => false,
            'setting'  => false
        ]);

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Profile.css');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile');

        $this->setAttribute(
            'data-qui',
            'package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile'
        );
    }

    /**
     * Return the control body
     *
     * @return string
     */
    public function getBody()
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return '';
        }

        $Request    = QUI::getRequest();
        $categories = Utils::getProfileCategorySettings();

        $currentCategory = $this->getAttribute('category');
        $currentSetting  = $this->getAttribute('settings');

        if (empty($categories)) {
            return '';
        }

        /**
         * @param $array
         * @return int|string|false
         */
        $getFirstCategory = function ($array) {
            if (empty($array)) {
                return false;
            }

            reset($array);

            return key($array);
        };

        /**
         * @param $array
         * @param bool $category
         * @return bool
         */
        $getFirstCategorySetting = function ($array, $category = false) use ($getFirstCategory) {
            if ($category === false) {
                $category = $getFirstCategory($array);
            }

            if (!isset($array[$category])) {
                return false;
            }

            $data = $array[$category];

            return $data['items'][0]['name'];
        };

        // check view permissions for each category / setting
        foreach ($categories as $key => $category) {
            foreach ($category['items'] as $k => $setting) {
                if (!Utils::hasPermissionToViewCategory($category['name'], $setting['name'])) {
                    unset($categories[$key]['items'][$k]);

                    if ($currentCategory === $category['name']
                        && $currentSetting === $setting['name']) {
                        $currentSetting = false;
                    }
                }
            }

            if (empty($categories[$key]['items'])) {
                unset($categories[$key]);
            } else {
                $categories[$key]['items'] = array_values($categories[$key]['items']);
            }
        }

        if (empty($currentCategory)) {
            $currentCategory = $getFirstCategory($categories);
        }

        if (empty($currentSetting)) {
            $currentSetting = $getFirstCategorySetting($categories, $currentCategory);
        }

        // find the current control
        $Control = false;

        if ($currentCategory && $currentSetting) {
            try {
                $Control = QUI\FrontendUsers\Utils::getProfileSettingControl(
                    $currentCategory,
                    $currentSetting
                );

                $Control->setAttribute('User', $this->getUser());

                if ($Request->request->get('profile-save')) {
                    try {
                        $Control->onSave();
                    } catch (QUI\FrontendUsers\Exception $Exception) {
                        $Engine->assign('Error', $Exception);
                    }
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        // load the translations
        $categories = Utils::loadTranslationForCategories($categories);

        $Engine->assign([
            'categories'      => $categories,
            'currentCategory' => $currentCategory,
            'currentSetting'  => $currentSetting,
            'Category'        => $Control,
            'Site'            => $this->getSite()
        ]);

        return $Engine->fetch(dirname(__FILE__).'/Profile.html');
    }

    /**
     * Return the User
     *
     * @return QUI\Interfaces\Users\User
     * @throws QUI\FrontendUsers\Exception
     */
    public function getUser()
    {
        $User = $this->getAttribute('User');

        if ($User === false) {
            throw new QUI\FrontendUsers\Exception([
                'quiqqer/frontend-users',
                'exception.ser.was.not.net'
            ]);
        }

        if ($User instanceof QUI\Interfaces\Users\User) {
            return $User;
        }

        throw new QUI\FrontendUsers\Exception([
            'quiqqer/frontend-users',
            'exception.ser.was.not.net'
        ]);
    }

    /**
     * Return the current site
     *
     * @return QUI\Projects\Site
     */
    public function getSite()
    {
        if ($this->getAttribute('Site')) {
            return $this->getAttribute('Site');
        }

        return QUI::getRewrite()->getSite();
    }
}
