<?php

namespace QUI\FrontendUsers\Controls\Profile;

/**
 * Interface ControlInterface
 * @package QUI\FrontendUsers\Controls\Profile
 */
interface ControlInterface
{
    /**
     * Method is called, when on save is triggered
     */
    public function onSave();

    /**
     * Validate the send data
     *
     * @return mixed
     */
    public function validate();
}
