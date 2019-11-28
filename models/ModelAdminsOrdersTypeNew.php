<?php
/**
 *
 */
class ModelAdminsOrdersTypeNew extends ModelAbstractAdminsOrdersType
{
    protected $_table = 'adminsOrdersTypeNew';

    function __construct()
    {
        $this->_fields = [
            'orderId'           => [ Vars::UBIGINT, [1] ],
            'complexity'        => [ Vars::ENUM, self::getComplexities() ],
            'dataSnapshotId'    => [ Vars::UBIGINT ],
            'currPairId'        => [ Vars::UINT, [1] ],
            'amountPrice'       => [ Vars::UFLOAT ],
            'amountMultiplier'  => [ Vars::ENUM, self::getAmountMultipliers() ],
            'amount'            => [ Vars::UFLOAT ],
            'remain'            => [ Vars::UFLOAT ],
            'price'             => [ Vars::UFLOAT, [self::PRICE_MIN] ],
            'priceAvgExec'      => [ Vars::UFLOAT ],
            'side'              => [ Vars::ENUM, self::getSides() ],
            'exec'              => [ Vars::ENUM, self::getExecs() ],
            'fee'               => [ Vars::UFLOAT ],
            'availableInUsdSum' => [ Vars::UFLOAT ],
            'created'           => [ Vars::DATETIME ],
            'updated'           => [ Vars::DATETIME ],
        ];

        parent::__construct();
    }

    function insert(
        int $orderId,
        string $complexity,
        int $dataSnapshotId,
        int $currPairId,
        float $amountPrice,
        float $amountMultiplier,
        float $amount,
        float $price,
        string $side,
        string $exec
    )
    {
        $this->_insert([
            'orderId'           => $orderId,
            'complexity'        => $complexity,
            'dataSnapshotId'    => $dataSnapshotId,
            'currPairId'        => $currPairId,
            'amountPrice'       => $amountPrice,
            'amountMultiplier'  => $amountMultiplier,
            'amount'            => $amount,
            'remain'            => $amount,
            'price'             => $price,
            'side'              => $side,
            'exec'              => $exec,
        ]);
    }

    function updateAmount(int $orderId, float $amount)
    {
        $this->_updateAmount($orderId, $amount);
    }

    function updateAvailableInUsdSum(int $orderId, float $availableInUsdSum)
    {
        $this->_updateAvailableInUsdSum($orderId, $availableInUsdSum);
    }

    function updateRemain(int $orderId, float $remain)
    {
        $this->_updateRemain($orderId, $remain);
    }

    function updatePriceAvgExec(int $id, float $price)
    {
        $this->_updatePriceAvgExec($id, $price);
    }

    function updateFee(int $orderId, float $fee)
    {
        $this->_updateFee($orderId, $fee);
    }
}