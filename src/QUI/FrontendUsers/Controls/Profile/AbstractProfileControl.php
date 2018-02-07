<?php

namespace QUI\FrontendUsers\Controls\Profile;

use QUI;

/**
 * Class AbstractProfileControl
 *
 * For all Frontend Users Profile Controls
 */
abstract class AbstractProfileControl extends QUI\Control implements ControlInterface
{
    /**
     * Method is called, when on save is triggered
     *
     * @return mixed|void
     */
    public function onSave()
    {
        // nothing
    }

    /**
     * Validate the send data
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function validate()
    {
        // nothing
    }
}
