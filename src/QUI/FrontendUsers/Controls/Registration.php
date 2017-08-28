<?php

/**
 * This file contains QUI\FrontendUsers\Controls\Registration
 */

namespace QUI\FrontendUsers\Controls;

use QUI;

/**
 * Class Registration
 * - Registration Display
 * - Display all Registration Control
 * - GUI for the Registration
 *
 * @package QUI\FrontendUsers\Controls
 */
class Registration extends QUI\Control
{
    /**
     * Registration constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Registration.css');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getBody()
    {
        $Engine       = QUI::getTemplateManager()->getEngine();
        $Registrators = QUI\FrontendUsers\Handler::getInstance()->getRegistrators();

        try {
            $this->register();
        } catch (QUI\Exception $Exception) {
            $Engine->assign('Error', $Exception->getMessage());
        }

        $Engine->assign(array(
            'Registrators' => $Registrators
        ));

        return $Engine->fetch(dirname(__FILE__).'/Registration.html');
    }

    /**
     * Is registration started?
     *
     * @return bool|QUI\FrontendUsers\RegistratorInterface
     */
    protected function isCurrentlyExecuted()
    {
        if (!isset($_POST['registration'])) {
            return false;
        }

        if (!isset($_POST['registrator'])) {
            return false;
        }

        $FrontendUsers = QUI\FrontendUsers\Handler::getInstance();
        $Registrators  = $FrontendUsers->getRegistrators();

        foreach ($Registrators as $Registrator) {
            if (get_class($Registrator) === $_POST['registrator']) {
                return $Registrator;
            }
        }

        return false;
    }

    /**
     * Execute the Registration
     *
     * @throws QUI\FrontendUsers\Exception
     */
    public function register()
    {
        if (!isset($_POST['registration'])) {
            return;
        }

        $Registrator = $this->isCurrentlyExecuted();

        if ($Registrator === false) {
            throw new QUI\FrontendUsers\Exception(array(
                'quiqqer/frontend-user',
                'exception.registrator.not.exists'
            ));
        }

        $Registrator->setAttributes($_POST);
        $Registrator->validate();
    }
}