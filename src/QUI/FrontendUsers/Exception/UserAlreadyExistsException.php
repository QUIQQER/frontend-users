<?php

namespace QUI\FrontendUsers\Exception;

use QUI\FrontendUsers\Exception;

class UserAlreadyExistsException extends Exception
{
    protected $code = 50001;
}
