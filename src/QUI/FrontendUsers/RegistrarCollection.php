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
class RegistrarCollection extends QUI\Collection
{
    /**
     * RegistrarCollection constructor.
     * @param array $children
     */
    public function __construct(array $children = [])
    {
        $this->allowed = [
            AbstractRegistrar::class
        ];

        parent::__construct($children);
    }
}
