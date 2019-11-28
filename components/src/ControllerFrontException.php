<?php
/**
 *
 */
class ControllerFrontException extends Exception
{
    const E_DEFAULT             = 13000;
    const E_BAD_REQUEST         = 13001;
    const E_UNDEFINED_URL_ROUTE = 13002;

    function __construct(string $msg, int $code = self::E_DEFAULT)
    {
        parent::__construct($msg, $code);
    }
}