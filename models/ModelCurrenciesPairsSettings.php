<?php
/**
 *
 */
class ModelCurrenciesPairsSettings extends Model
{
    protected $_table = 'currenciesPairsSettings';

    function __construct()
    {
        $this->_fields = [
            'currPairId'    => [ Vars::UINT, [1] ],
            'ordersCount'   => [ Vars::UINT, [1, 10] ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getSettingsRows(int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            ORDER BY currPairId ASC 
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getSettingsRowByPairId(int $currPairId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%",
            ['%cols%' => $cols],
            ['%where%' => [
                'currPairId' => $currPairId,
            ]]
        )->row();
    }

    function insert(int $currPairId, int $ordersCount)
    {
        $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'currPairId' => $currPairId,
                'ordersCount' => $ordersCount,
            ])]
        )->exec();
    }

    function update(int $currPairId, int $ordersCount)
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set%
            WHERE %where%",
            ['%set%' => $this->filter([
                'ordersCount' => $ordersCount,
            ])],
            ['%where%' => [
                'currPairId' => $currPairId,
            ]]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }
}