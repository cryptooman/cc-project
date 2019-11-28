<?php
/**
 *
 */
abstract class ModelAbstractAdminsOrdersType extends ModelAbstractAdminsOrders
{
    static function getModel(string $type): ModelAbstractAdminsOrdersType
    {
        if ($type == self::TYPE_NEW) {
            return ModelAdminsOrdersTypeNew::inst();
        }
        elseif ($type == self::TYPE_REPLACE) {
            return ModelAdminsOrdersTypeReplace::inst();
        }
        elseif ($type == self::TYPE_CANCEL) {
            return ModelAdminsOrdersTypeCancel::inst();
        }
        else {
            throw new Err("Bad order type [$type]");
        }
    }

    function getOrderByOrderId(int $orderId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE orderId = :orderId",
            ['%cols%' => $cols],
            ['orderId' => $orderId]
        )->row();
    }

    protected function _insert(array $data)
    {
        $this->query(
            "INSERT INTO $this->_table
            SET %set%, created = NOW()",
            ['%set%' => $this->filter($data)]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _updateAmount(int $orderId, float $amount)
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set%
            WHERE %where%",
            ['%set%' => $this->filter(['amount' => $amount])],
            ['%where%' => ['orderId' => $orderId]]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _updateAvailableInUsdSum(int $orderId, float $availableInUsdSum)
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set%
            WHERE %where%",
            ['%set%' => $this->filter(['availableInUsdSum' => $availableInUsdSum])],
            ['%where%' => ['orderId' => $orderId]]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _updateRemain(int $orderId, float $remain)
    {
        $this->query(
            "UPDATE $this->_table 
            SET remain = :remain
            WHERE orderId = :orderId",
            $this->filter(['remain' => $remain]),
            ['orderId' => $orderId]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _updatePriceAvgExec(int $orderId, float $price)
    {
        $this->query(
            "UPDATE $this->_table 
            SET priceAvgExec = :priceAvgExec
            WHERE orderId = :orderId",
            $this->filter(['priceAvgExec' => $price]),
            ['orderId' => $orderId]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _updateFee(int $orderId, float $fee)
    {
        $this->query(
            "UPDATE $this->_table 
            SET fee = :fee
            WHERE orderId = :orderId",
            $this->filter(['fee' => $fee]),
            ['orderId' => $orderId]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }
}