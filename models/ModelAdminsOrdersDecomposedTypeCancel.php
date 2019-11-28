<?php
/**
 *
 */
class ModelAdminsOrdersDecomposedTypeCancel extends ModelAbstractAdminsOrdersDecomposed
{
    protected $_table = 'adminsOrdersDecomposedTypeCancel';

    function __construct()
    {
        $this->_fields = [
            'id'                        => [ Vars::UBIGINT, [1] ],
            'orderId'                   => [ Vars::UBIGINT, [1] ],
            'cancelOrderDecomposedId'   => [ Vars::UBIGINT, [1] ],
            'systemApiKeyId'            => [ Vars::UINT ],
            'userApiKeyId'              => [ Vars::UINT ],
            'exchangeId'                => [ Vars::UINT, [1] ],
            'requestStrId'              => [ Vars::HASH, [40, 40] ],
            'requestGroupStrId'         => [ Vars::HASH, [40, 40] ],
            'status'                    => [ Vars::ENUM, self::getStatuses() ],
            'statusCode'                => [ Vars::ENUM, self::getStatusCodes() ],
            'statusMsg'                 => [ Vars::STR, [0], function($v) { return Str::cutAddDots($v, 2048); } ],
            'enabled'                   => [ Vars::BOOL ],
            'created'                   => [ Vars::DATETIME ],
            'updated'                   => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function insert(int $orderId, array $orders)
    {
        if (!$orders) {
            throw new Err("Empty orders");
        }

        $cancelOrderDecomposedIdsSeen = [];
        foreach ($orders as $order) {
            if (isset($cancelOrderDecomposedIdsSeen[$order['cancelOrderDecomposedId']])) {
                throw new Err("Already seen cancelOrderDecomposedId [%s]", $order['cancelOrderDecomposedId']);
            }
            $cancelOrderDecomposedIdsSeen[$order['cancelOrderDecomposedId']] = true;
        }

        $this->_insert(
            $orderId,
            [
                'DEFAULT',          // id
                ':orderId',
                ':cancelOrderDecomposedId',
                ':systemApiKeyId',
                ':userApiKeyId',
                ':exchangeId',
                ':requestStrId',
                ':requestGroupStrId',
                'DEFAULT',          // status
                'DEFAULT',          // statusCode
                'DEFAULT',          // statusMsg
                'DEFAULT',          // enabled
                'NOW()',            // created
                'DEFAULT',          // updated
            ],
            $orders
        );
    }
}