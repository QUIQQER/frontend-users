<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile
 */

namespace QUI\FrontendUsers\Controls;

use QUI;
use QUI\Control;

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

        $this->addCSSFile(dirname(__FILE__).'/Profile.css');
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
        $current    = false;
        $categories = QUI\FrontendUsers\Utils::getProfileCategories();

        \QUI\System\Log::writeRecursive($categories);

        if (QUI::getRequest()->get('category')) {
            $this->setAttribute('category', QUI::getRequest()->get('category'));
        }

        if ($this->getAttribute('category')) {
            try {
                QUI\FrontendUsers\Utils::getProfileCategory(
                    QUI::getRequest()->get('category')
                );

                $current = QUI::getRequest()->get('category');
            } catch (QUI\FrontendUsers\Exception $Exception) {
            }
        }

        if ($current === false) {
            $current = $categories[0]['name'];
        }

        $Control = QUI\FrontendUsers\Utils::getProfileCategoryControl($current);
        $Control->setAttribute('User', $this->getUser());

        if ($Request->request->get('profile-save')) {
            try {
                $Control->onSave();
            } catch (QUI\FrontendUsers\Exception $Exception) {
                $Engine->assign('Error', $Exception);
            }
        }

        $Engine->assign(array(
            'Site'       => $this->getSite(),
            'categories' => QUI\FrontendUsers\Utils::getProfileCategories(),
            'current'    => $current,
            'Category'   => $Control
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
