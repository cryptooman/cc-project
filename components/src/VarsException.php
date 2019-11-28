<?php
/**
 *
 */
class VarsException extends Exception
{
    const E_DEFAULT             = 12000;
    const E_EMPTY				= 12001;
    const E_BAD_TYPE			= 12002;
    const E_BAD_VALUE			= 12003;
    const E_RANGE_LESS		    = 12004;
    const E_RANGE_MORE		    = 12005;
    const E_MISMATCH_PATTERN	= 12006;
    const E_CALLBACK_FAILED	    = 12100;

    function __construct(string $msg, int $code = self::E_DEFAULT)
    {
        parent::__construct($msg, $code);
    }
}