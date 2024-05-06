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
    protected string $name;

    /**
     * Error message
     *
     * @var string
     */
    protected string $msg;

    /**
     * InvalidFormField constructor.
     *
     * @param string $name
     * @param string $msg
     */
    public function __construct(string $name, string $msg)
    {
        $this->name = $name;
        $this->msg = $msg;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getMsg(): string
    {
        return $this->msg;
    }
}
