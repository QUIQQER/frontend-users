<?php

namespace QUI\FrontendUsers\Exception;

use QUI\FrontendUsers\Exception;

class UserAlreadyExistsException extends Exception
{
    protected int $code = 50001;
}
