<?php
/**
 *
 */
class MysqlException extends Exception
{
    const E_DEFAULT             = 11000;
    // Most common errors (returned by mysql database)
    const E_DEAD_LOCK           = 1213;
    const E_LOCK_WAIT_TIMEOUT   = 1205;
    const E_SERVER_HAS_GONE     = 2006;
    const E_LOST_CONNECTION     = 2013;
    const E_DUPLICATE_ENTRY     = 1062;

    function __construct(string $message, int $code = self::E_DEFAULT, $pdoConnection = null)
    {
        if ($pdoConnection) {
            $pdoErr = $pdoConnection->errorInfo();
            if (!empty($pdoErr[1]) && !empty($pdoErr[2])) {
                $message = $message . ' (' . $pdoErr[2] . ')';
                $code = (int) $pdoErr[1]; // Overwrite class code with mysql database code
            }
        }
        parent::__construct($message, $code);
    }
}