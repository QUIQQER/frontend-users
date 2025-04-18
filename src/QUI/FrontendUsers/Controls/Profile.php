<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\Control;
use QUI\Exception;
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
            'settings' => false,
            'menu' => true
        ]);

        parent::__construct($attributes);

//        if (!defined('QUIQQER_CONTROL_TEMPLATE_USE_BASIC') || QUIQQER_CONTROL_TEMPLATE_USE_BASIC !== true) {
        $this->addCSSFile(dirname(__FILE__) . '/Profile.css');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile');
//        }

        $this->setAttribute(
            'data-qui',
            'package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile'
        );
    }

    /**
     * Return the control body
     *
     * @return string
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Request = QUI::getRequest();
        $categories = Utils::getProfileCategorySettings();

        $currentCategory = $this->getAttribute('category');
        $currentSetting = $this->getAttribute('settings');

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
        $getFirstCategorySetting = function ($array, bool | string | int $category = false) use ($getFirstCategory) {
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

                    if (
                        $currentCategory === $category['name']
                        && $currentSetting === $setting['name']
                    ) {
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

                if (!$Control) {
                    QUI\System\Log::addError('Control not found', [
                        'current-category' => $currentCategory,
                        'current-setting' => $currentSetting
                    ]);

                    return '';
                }

                if (method_exists($Control, 'setAttribute')) {
                    $Control->setAttribute('User', $this->getUser());
                }

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
            'categories' => $categories,
            'currentCategory' => $currentCategory,
            'currentSetting' => $currentSetting,
            'Category' => $Control,
            'Site' => $this->getSite(),
            'this' => $this
        ]);

        return $Engine->fetch($this->getTemplateFile());
    }

    /**
     * Return the User
     *
     * @return QUI\Interfaces\Users\User
     * @throws QUI\FrontendUsers\Exception
     */
    public function getUser(): QUI\Interfaces\Users\User
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
     * @return QUI\Interfaces\Projects\Site
     * @throws Exception
     */
    public function getSite(): QUI\Interfaces\Projects\Site
    {
        if ($this->getAttribute('Site')) {
            return $this->getAttribute('Site');
        }

        return QUI::getRewrite()->getSite();
    }
}
