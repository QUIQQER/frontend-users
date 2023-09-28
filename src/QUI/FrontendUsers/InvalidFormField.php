<?php

namespace QUI\FrontendUsers;

/**
 * Class InvalidFormField
 *
 * Represents invalid form fields in registration forms
 *
 * @package QUI\FrontendUsers
 */
class InvalidFormField
{
    /**
     * Form field name
     *
     * @var string
     */
    protected $name;

    /**
     * Error message
     *
     * @var string
     */
    protected $msg;

    /**
     * InvalidFormField constructor.
     *
     * @param string $name
     * @param string $msg
     */
    public function __construct($name, $msg)
    {
        $this->name = $name;
        $this->msg = $msg;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getMsg()
    {
        return $this->msg;
    }
}
