<?php
/**
 *
 */
class ModelAdminsOrdersDataSnapshots extends Model
{
    protected $_table = 'adminsOrdersDataSnapshots';

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UBIGINT, [1] ],
            'orderId'           => [ Vars::UBIGINT, [1] ],
            'systemBalances'    => [ Vars::RAWSTR, [1] ],
            'usersBalances'     => [ Vars::RAWSTR, [1] ],
            'currPairsRatios'   => [ Vars::RAWSTR, [1] ],
            'enabled'           => [ Vars::BOOL ],
            'created'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getSnapshots(int $limit, array $cols = ['*']): array
    {
        $rows = $this->_getRows($limit, $cols);
        return $this->_formatRows($rows, $cols);
    }

    function getLatestActiveSnapshots(int $limit, array $cols = ['*']): array
    {
        $rows = $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE enabled = 1
            ORDER BY id DESC 
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
        return $this->_formatRows($rows, $cols);
    }

    function getSnapshotById(int $id, array $cols = ['*']): array
    {
        $row = $this->_getRowById($id, $cols);
        return $this->_formatRow($row, $cols);
    }

    function getActiveSnapshotById(int $id, array $cols = ['*']): array
    {
        $row = $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE id = :id AND enabled = 1",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
        return $this->_formatRow($row, $cols);
    }

    function getActiveSnapshotByOrderId(int $orderId, array $cols = ['*']): array
    {
        $row = $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE orderId = :orderId AND enabled = 1",
            ['%cols%' => $cols],
            ['orderId' => $orderId]
        )->row();
        return $this->_formatRow($row, $cols);
    }

    function insert(int $orderId, array $systemBalances, array $usersBalances, array $currPairsRatios): int
    {
        return $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'orderId'           => $orderId,
                'systemBalances'    => Json::encode($systemBalances),
                'usersBalances'     => Json::encode($usersBalances),
                'currPairsRatios'   => Json::encode($currPairsRatios),
            ])]
        )->exec()->lastId();
    }

    private function _formatRows(array $rows, array $cols): array
    {
        foreach ($rows as &$row) {
            $row = $this->_formatRow($row, $cols);
        } unset($row);

        return $rows;
    }

    private function _formatRow(array $row, array $cols): array
    {
        if ($row) {
            if ($cols = ['*'] || in_array('systemBalances', $cols)) {
                $row['systemBalances'] = Json::decode($row['systemBalances']);
            }
            if ($cols = ['*'] || in_array('usersBalances', $cols)) {
                $row['usersBalances'] = Json::decode($row['usersBalances']);
            }
            if ($cols = ['*'] || in_array('currPairsRatios', $cols)) {
                $row['currPairsRatios'] = Json::decode($row['currPairsRatios']);
            }
        }
        return $row;
    }
}