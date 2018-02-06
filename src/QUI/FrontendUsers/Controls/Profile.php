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
    public function __construct(array $attributes = array())
    {
        $this->setAttributes(array(
            'category' => false,
            'setting'  => false
        ));

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
        $Request    = QUI::getRequest();
        $Engine     = QUI::getTemplateManager()->getEngine();
        $categories = Utils::getProfileCategorySettings();

        $currentCategory = $this->getAttribute('category');
        $currentSetting  = $this->getAttribute('settings');

        if (empty($categories)) {
            return '';
        }

        /**
         * @param $array
         * @return int|null|string
         */
        $getFirstCategory = function ($array) {
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

        foreach ($categories as $key => $category) {
            if (!Utils::hasPermissionToViewCategory($category['name'])) {
                unset($categories[$key]);
            }
        }

        if ($currentCategory
            && $currentSetting
            && Utils::hasPermissionToViewCategory($currentCategory, $currentSetting)) {
            try {
                QUI\FrontendUsers\Utils::getProfileSetting($currentCategory, $currentSetting);
            } catch (QUI\FrontendUsers\Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                $currentCategory = false;
                $currentSetting  = false;
            }
        }

        if ($currentCategory === false) {
            $currentCategory = $getFirstCategory($categories);
        }

        if ($currentSetting === false) {
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
        $categories = utils::loadTranslationForCategories($categories);

        $Engine->assign(array(
            'categories'      => $categories,
            'currentCategory' => $currentCategory,
            'currentSetting'  => $currentSetting,
            'Category'        => $Control,
            'Site'            => $this->getSite()
        ));

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
            throw new QUI\FrontendUsers\Exception(array(
                'quiqqer/frontend-users',
                'exception.ser.was.not.net'
            ));
        }

        if ($User instanceof QUI\Interfaces\Users\User) {
            return $User;
        }

        throw new QUI\FrontendUsers\Exception(array(
            'quiqqer/frontend-users',
            'exception.ser.was.not.net'
        ));
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
