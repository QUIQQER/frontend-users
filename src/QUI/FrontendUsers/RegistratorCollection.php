<?php

/**
 * This file contains \QUI\FrontendUsers\Exception
 */

namespace QUI\FrontendUsers;

use QUI;

/**
 * Class Exception
 *
 * @package QUI\FrontendUsers
 */
class RegistratorCollection extends QUI\Collection
{
    /**
     * RegistratorCollection constructor.
     * @param array $children
     */
    public function __construct(array $children = array())
    {
        $this->allowed = array(
            AbstractRegistrator::class
        );

        parent::__construct($children);
    }
}
