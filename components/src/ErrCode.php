<?php
/**
 *
 */
class ErrCode
{
    private $_code = 0;

    function __construct(int $code)
    {
        $this->_code = $code;
    }

    function getCode(): int
    {
        return $this->_code;
    }
}