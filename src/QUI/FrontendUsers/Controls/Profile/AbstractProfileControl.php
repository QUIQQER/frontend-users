<?php

namespace QUI\FrontendUsers\Controls\Profile;

use Exception;
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
     * @return void
     */
    public function onSave(): void
    {
        // nothing
    }

    /**
     * Validate the send data
     *
     * @return void
     * @throws Exception
     */
    public function validate(): void
    {
        // nothing
    }
}
