<?php
/**
 *
 */
class UserErr extends Exception
{
    const E_DEFAULT = 100000;

    protected $_extra = [];

    function __construct(string $msg, array $extra = [], int $code = self::E_DEFAULT)
    {
        $this->_extra = $extra;
        parent::__construct($msg, $code);
    }

    function getExtra()
    {
        return $this->_extra;
    }
}