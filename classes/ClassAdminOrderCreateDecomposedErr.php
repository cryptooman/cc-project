<?php
/**
 *
 */
class ClassAdminOrderCreateDecomposedErr extends Err
{
    private static $_orderId = 0;

    static function set(int $orderId)
    {
        if ($orderId <= 0) {
            throw new Err("Bad order id [$orderId]");
        }
        self::$_orderId = $orderId;
    }

    function __construct(string $msg, ...$args)
    {
        parent::__construct("Order id [" . self::$_orderId . "]: " . $msg, ...$args);
    }
}