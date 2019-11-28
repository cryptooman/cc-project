<?php
/**
 *
 */
class ModelCurrenciesPairsRatios extends Model
{
    protected $_table = 'currenciesPairsRatios';

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UINT, [1] ],
            'currPairId'        => [ Vars::UINT, [1] ],
            'exchangeId'        => [ Vars::UINT, [1] ],
            'ratio'             => [ Vars::UFLOAT ],
            'syncedAt'          => [ Vars::DATETIME ],
            'exchangeTs'        => [ Vars::UINT ],
            'created'           => [ Vars::DATETIME ],
            'updated'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getRatios(int $limit, array $cols = ['*']): array
    {
        return $this->_getRows($limit, $cols);
    }

    function getRatiosByPairId(int $currPairId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%",
            ['%cols%' => $cols],
            ['%where%' => [
                'currPairId' => $currPairId,
            ]]
        )->rows();
    }

    function getRatioById(int $id, array $cols = ['*']): array
    {
        return $this->_getRowById($id, $cols);
    }

    function getRatioByPairIdExchangeId(int $currPairId, int $exchangeId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%",
            ['%cols%' => $cols],
            ['%where%' => [
                'currPairId' => $currPairId,
                'exchangeId' => $exchangeId,
            ]]
        )->row();
    }

    function insert(int $currPairId, int $exchangeId): int
    {
        return $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'currPairId' => $currPairId,
                'exchangeId' => $exchangeId,
            ])]
        )->exec()->lastId();
    }

    function update(int $currPairId, int $exchangeId, float $ratio, int $exchangeTs)
    {
        if ($ratio <= 0 || $exchangeTs <= 0) {
            throw new Err("Bad data to update ratio: ", func_get_args());
        }
        $this->query(
            "UPDATE $this->_table 
            SET %set%, syncedAt = NOW()
            WHERE %where%",
            ['%set%' => $this->filter([
                'ratio' => $ratio,
                'exchangeTs' => $exchangeTs,
            ])],
            ['%where%' => [
                'currPairId' => $currPairId,
                'exchangeId' => $exchangeId,
            ]]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }
}