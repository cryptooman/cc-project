<?php
/**
 *
 */
abstract class ModelAbstractAdminsOrdersDecomposed extends ModelAbstractAdminsOrders
{
    const REQUESTER_TYPE = ModelExchangesRequestsStack::REQUESTER_TYPE_ORDER;

    static function getModel(string $type): ModelAbstractAdminsOrdersDecomposed
    {
        if ($type == self::TYPE_NEW) {
            return ModelAdminsOrdersDecomposedTypeNew::inst();
        }
        elseif ($type == self::TYPE_REPLACE) {
            return ModelAdminsOrdersDecomposedTypeReplace::inst();
        }
        elseif ($type == self::TYPE_CANCEL) {
            return ModelAdminsOrdersDecomposedTypeCancel::inst();
        }
        else {
            throw new Err("Bad order type [$type]");
        }
    }

    function getOrdersByOrderId(int $orderId, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE orderId = :orderId
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['orderId' => $orderId]
        )->rows();
    }

    function getActiveOrdersByOrderId(int $orderId, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE orderId = :orderId
                  AND status IN(" . $this->_getActiveStatusesIn() . ") 
                  AND enabled = 1
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['orderId' => $orderId]
        )->rows();
    }

    function getActiveOrdersByOrderIdAndStatus(int $orderId, string $status, int $limit, array $cols = ['*']): array
    {
        $this->_isStatusActive($status);
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where% AND enabled = 1
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['%where%' => [
                'orderId' => $orderId,
                'status' => $status
            ]]
        )->rows();
    }

    function getActiveOrdersByOrderIdAndStatusCodes(int $orderId, array $statusCodes, int $limit, array $cols = ['*']): array
    {
        $statusCodesIn = [];
        foreach ($statusCodes as $statusCode) {
            $this->_isStatusCodeActive($statusCode);
            $statusCodesIn[] = $this->quoteValue($this->filterOne('statusCode', $statusCode));
        }
        $statusCodesIn = join(', ', $statusCodesIn);
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE orderId = :orderId 
                  AND statusCode IN($statusCodesIn)
                  AND enabled = 1
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['orderId' => $orderId]
        )->rows();
    }

    function getOrderById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE id = :id",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    function updateStatus(int $id, string $status, int $statusCode, string $statusMsg = '', $affectedRows = 1)
    {
        $this->query(
            "UPDATE $this->_table
            SET %set%
            WHERE id = :id",
            ['%set%' => $this->filter([
                'status' => $status,
                'statusCode' => $statusCode,
                'statusMsg' => $statusMsg,
            ])],
            ['id' => $id]
        )->exec()->affectedRows($affectedRows);
    }

    function updateStatusesByOrderId(int $orderId, string $status, int $statusCode, string $statusMsg = '')
    {
        $this->query(
            "UPDATE $this->_table
            SET %set%
            WHERE orderId = :orderId",
            ['%set%' => $this->filter([
                'status' => $status,
                'statusCode' => $statusCode,
                'statusMsg' => $statusMsg,
            ])],
            ['orderId' => $orderId]
        )->exec()->affectedRows(self::AFFECTED_ONE_OR_MORE);
    }

    function setExchangeOrderId(int $id, string $exchangeOrderId)
    {
        $this->query(
            "UPDATE $this->_table
            SET exchangeOrderId = :exchangeOrderId
            WHERE id = :id AND enabled = 1",
            $this->filter(['exchangeOrderId' => $exchangeOrderId]),
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    // TODO
    function disable(int $id)
    {
    }

    function disableByOrderId(int $orderId)
    {
        $this->query(
            "UPDATE $this->_table
            SET enabled = 0
            WHERE orderId = :orderId AND enabled = 1",
            ['orderId' => $orderId]
        )->exec()->affectedRows(self::AFFECTED_ONE_OR_MORE);
    }

    protected function _insert(int $orderId, array $fields, array $orders)
    {
        if (!$orders) {
            throw new Err("Empty orders");
        }

        $totalOrders = count($orders);
        foreach ($orders as $i => &$order) {
            if (!ModelAbstractApiKeys::checkIds($order['systemApiKeyId'], $order['userApiKeyId'], false)) {
                throw new Err("Bad order: Must be set systemApiKeyId or userApiKeyId: ", $order);
            }
            if (!empty($order['orderId'])) {
                throw new Err("Bad order: Expect to take orderId from provided arg: ", $order);
            }
            $order['orderId'] = $orderId;
            $order['requestStrId'] = ModelExchangesRequestsStack::makeStrId(self::REQUESTER_TYPE, $orderId, ClassDateTime::microTime(''));
            $order['requestGroupStrId'] = ModelExchangesRequestsStack::makeStrId(self::REQUESTER_TYPE, $orderId);
            $order = $this->filter($order);
        }
        unset($order);

        $inserted = $this->insertBulk(
            "INSERT INTO $this->_table
            VALUES %values%",
            $fields,
            $orders
        );
        if ($inserted != $totalOrders) {
            throw new Err("Failed to insert rows: ", $orders);
        }
    }

    protected function _updateRemain(int $id, float $remain)
    {
        $this->query(
            "UPDATE $this->_table
            SET remain = :remain
            WHERE id = :id AND enabled = 1",
            $this->filter(['remain' => $remain]),
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _updatePriceAvgExec(int $id, float $price)
    {
        $this->query(
            "UPDATE $this->_table
            SET priceAvgExec = :priceAvgExec
            WHERE id = :id AND enabled = 1",
            $this->filter(['priceAvgExec' => $price]),
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _updateFee(int $id, float $fee)
    {
        $this->query(
            "UPDATE $this->_table
            SET fee = :fee
            WHERE id = :id AND enabled = 1",
            $this->filter(['fee' => $fee]),
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }
}