<?php

namespace QUI\FrontendUsers\Exception;

use QUI\FrontendUsers\Exception;

class EmailAddressNotVerifiableException extends Exception
{
    protected $code = 50002;
}
