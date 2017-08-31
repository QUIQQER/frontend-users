<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Profile\ControlWrapper
 */

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;
use QUI\Control;

/**
 * Class ControlWrapper
 * - Its a wrapper to display only templates
 *
 * @package QUI\FrontendUsers\Controls\Profile
 */
class ControlWrapper extends Control implements ControlInterface
{
    /**
     * ControlWrapper constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-frontendUsers-controls-profile-control');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine   = QUI::getTemplateManager()->getEngine();
        $template = $this->getAttribute('template');

        if (!file_exists($template)) {
            return '';
        }

        if ($this->getAttribute('User') === false) {
            $this->setAttribute('User', QUI::getUserBySession());
        }

        $Engine->assign(array(
            'User' => $this->getAttribute('User')
        ));

        return $Engine->fetch($template);
    }

    /**
     * event: on save
     */
    public function onSave()
    {
        $Request = QUI::getRequest();

        if (!$Request->request->get('profile-save')) {
            return;
        }

        /* @var $User QUI\Interfaces\Users\User */
        $data = $Request->request->all();
        $User = $this->getAttribute('User');

        unset($data['profile-save']);

        // email muss validiert werden, daher nicht einfach speichern
        // @todo validierungs step umsetzen @peat mit mor reden
        if (isset($data['email'])) {
            unset($data['email']);
        }

        $User->setAttributes($data);
        $User->save();
    }


    public function validate()
    {

    }
}
