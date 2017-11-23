<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\Control;
use QUI\FrontendUsers\Utils;

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
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        $this->setAttributes(array(
            'category' => false
        ));

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Profile.css');
        $this->addCSSClass('quiqqer-frontendUsers-controls-profile');
        $this->setAttribute('data-qui', 'package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile');
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
        $current    = $this->getAttribute('category');
        $categories = Utils::getProfileCategories();

        foreach ($categories as $k => $c) {
            if (!Utils::hasPermissionToViewCategory($c['name'])) {
                unset($categories[$k]);
            }
        }

        if (!empty($_GET['c'])) {
            $current = $_GET['c'];
        }

        if ($current && Utils::hasPermissionToViewCategory($current)) {
            $this->setAttribute('category', $current);

            try {
                QUI\FrontendUsers\Utils::getProfileCategory($current);
            } catch (QUI\FrontendUsers\Exception $Exception) {
                // category does not exist
                $current = false;
            }
        }

        if ($current === false) {
            $current = $categories[0]['name'];
        }

        $Control = false;

        if ($current) {
            $Control = QUI\FrontendUsers\Utils::getProfileCategoryControl($current);
            $Control->setAttribute('User', $this->getUser());

            if ($Request->request->get('profile-save')) {
                try {
                    $Control->onSave();
                } catch (QUI\FrontendUsers\Exception $Exception) {
                    $Engine->assign('Error', $Exception);
                }
            }
        }

        $Site = $this->getSite();

        foreach ($categories as $k => $c) {
            $categories[$k]['url'] = $Site->getUrlRewritten(array(), array(
                'c' => $c['name']
            ));
        }

        $Engine->assign(array(
            'categories' => $categories,
            'current'    => $current,
            'Category'   => $Control
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Profile.html');
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
