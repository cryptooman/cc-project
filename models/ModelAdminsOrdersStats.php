<?php
/**
 *
 */
class ModelAdminsOrdersStats extends ModelAbstractAdminsOrders
{
    protected $_table = 'adminsOrdersStats';

    function __construct()
    {
        $this->_fields = [
            'orderId'                   => [ Vars::UBIGINT, [1] ],
            'ordersDecomposedTotal'     => [ Vars::UINT ],
            'ordersDecomposedNew'       => [ Vars::UINT ],
            'ordersDecomposedDoing'     => [ Vars::UINT ],
            'ordersDecomposedCompleted' => [ Vars::UINT ],
            'ordersDecomposedRejected'  => [ Vars::UINT ],
            'ordersDecomposedFailed'    => [ Vars::UINT ],
            'ordersDecomposedSpecial'   => [ Vars::UINT ],
            'created'                   => [ Vars::DATETIME ],
            'updated'                   => [ Vars::DATETIME ],
        ];

        parent::__construct();
    }

    function getStatsByOrderId(int $orderId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE orderId = :orderId",
            ['%cols%' => $cols],
            ['orderId' => $orderId]
        )->row();
    }

    function insert(int $orderId)
    {
        $this->query(
            "INSERT INTO $this->_table
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'orderId' => $orderId,
            ])]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    function updateStats(int $orderId, array $stats)
    {
        $this->beginTransaction();

        $this->query(
            "UPDATE $this->_table
            SET %set%
            WHERE orderId = :orderId",
            ['%set%' => $this->filter($stats)],
            ['orderId' => $orderId]
        )->exec()->affectedRows(self::AFFECTED_ANY);

        $orderStats = $this->getStatsByOrderId($orderId);
        if (
            !$orderStats
            || $orderStats['ordersDecomposedTotal'] <= 0
            || $orderStats['ordersDecomposedTotal'] !=
                ($orderStats['ordersDecomposedNew']
                + $orderStats['ordersDecomposedDoing']
                + $orderStats['ordersDecomposedCompleted']
                + $orderStats['ordersDecomposedRejected']
                + $orderStats['ordersDecomposedFailed']
                + $orderStats['ordersDecomposedSpecial'])
        ) {
            throw new Err("Bad order [$orderId] stats: ", $orderStats);
        }

        $this->commit();
    }
}