<?php
/**
 *
 */
class ModelAdminsOrdersExchanges extends ModelAbstractAdminsOrders
{
    protected $_table = 'adminsOrdersExchanges';

    function __construct()
    {
        $this->_fields = [
            'orderId'       => [ Vars::UBIGINT, [1] ],
            'exchangeId'    => [ Vars::UINT, [1] ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];

        parent::__construct();
    }

    function getExchangesByOrderId(int $orderId, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE orderId = :orderId
            ORDER BY orderId ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['orderId' => $orderId]
        )->rows();
    }

    function getActiveExchangesByOrderId(int $orderId, int $limit): array
    {
        $cols = [];
        foreach (array_keys($this->_fields) as $colName) {
            $cols[] = "oe.$colName";
        }
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table AS oe
            LEFT JOIN " . ModelExchanges::inst()->table() . " AS e
            ON oe.exchangeId = e.id
            WHERE oe.orderId = :orderId AND e.enabled = 1
            ORDER BY oe.orderId ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['orderId' => $orderId]
        )->rows();
    }

    function insert(int $orderId, int $exchangeId)
    {
        $this->query(
            "INSERT INTO $this->_table
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'orderId'       => $orderId,
                'exchangeId'    => $exchangeId,
            ])]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }
}