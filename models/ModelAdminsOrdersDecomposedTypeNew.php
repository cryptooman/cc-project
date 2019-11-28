<?php
/**
 *
 */
class ModelAdminsOrdersDecomposedTypeNew extends ModelAbstractAdminsOrdersDecomposed
{
    protected $_table = 'adminsOrdersDecomposedTypeNew';

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UBIGINT, [1] ],
            'orderId'           => [ Vars::UBIGINT, [1] ],
            'systemApiKeyId'    => [ Vars::UINT ],
            'userApiKeyId'      => [ Vars::UINT ],
            'exchangeId'        => [ Vars::UINT, [1] ],
            'requestStrId'      => [ Vars::HASH, [40, 40] ],
            'requestGroupStrId' => [ Vars::HASH, [40, 40] ],
            'exchangeOrderId'   => [ Vars::REGX, ['!^[a-zA-Z0-9_\-]{0,40}$!'] ],
            'status'            => [ Vars::ENUM, self::getStatuses() ],
            'statusCode'        => [ Vars::ENUM, self::getStatusCodes() ],
            'statusMsg'         => [ Vars::STR, [0], function($v) { return Str::cutAddDots($v, 2048); } ],
            'currPairId'        => [ Vars::UINT, [1] ],
            'share'             => [ Vars::UFLOAT, [self::SHARE_MIN, self::SHARE_MAX] ],
            'amount'            => [ Vars::UFLOAT, [self::AMOUNT_MIN] ],
            'remain'            => [ Vars::UFLOAT ],
            'price'             => [ Vars::UFLOAT, [self::PRICE_MIN] ],
            'priceAvgExec'      => [ Vars::UFLOAT ],
            'side'              => [ Vars::ENUM, self::getSides() ],
            'exec'              => [ Vars::ENUM, self::getExecs() ],
            'fee'               => [ Vars::UFLOAT ],
            'enabled'           => [ Vars::BOOL ],
            'created'           => [ Vars::DATETIME ],
            'updated'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function insert(int $orderId, array $orders)
    {
        if (!$orders) {
            throw new Err("Empty orders");
        }

        foreach ($orders as &$order) {
            $order['share']  = NumFloat::floor($order['share']);
            $order['amount'] = NumFloat::floor($order['amount']);
            $order['price']  = NumFloat::floor($order['price']);
            $order['remain'] = $order['amount'];
        }
        unset($order);

        $this->_insert(
            $orderId,
            [
                'DEFAULT',          // id
                ':orderId',
                ':systemApiKeyId',
                ':userApiKeyId',
                ':exchangeId',
                ':requestStrId',
                ':requestGroupStrId',
                'DEFAULT',          // exchangeOrderId
                'DEFAULT',          // status
                'DEFAULT',          // statusCode
                'DEFAULT',          // statusMsg
                ':currPairId',
                ':share',
                ':amount',
                ':remain',
                ':price',
                'DEFAULT',          // priceAvgExec
                ':side',
                ':exec',
                'DEFAULT',          // fee
                'DEFAULT',          // enabled
                'NOW()',            // created
                'DEFAULT',          // updated
            ],
            $orders
        );
    }

    function updateRemain(int $id, float $remain)
    {
        $this->_updateRemain($id, NumFloat::floor($remain));
    }

    function updatePriceAvgExec(int $id, float $price)
    {
        $this->_updatePriceAvgExec($id, NumFloat::floor($price));
    }

    function updateFee(int $id, float $fee)
    {
        $this->_updateFee($id, NumFloat::floor($fee));
    }
}