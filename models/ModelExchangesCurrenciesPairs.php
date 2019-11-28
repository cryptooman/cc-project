<?php
/**
 *
 */
class ModelExchangesCurrenciesPairs extends Model
{
    const ORDER_AMOUNT_MIN = Cnst::UFLOAT_MIN_NON_ZERO;

    const ORDER_PRICE_MIN = 0.00001;
    const ORDER_PRICE_MAX = Cnst::INT32_MAX;

    protected $_table = 'exchangesCurrenciesPairs';

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UINT, [1] ],
            'exchangeId'        => [ Vars::UINT, [1] ],
            'currPairId'        => [ Vars::UINT, [1] ],
            'orderAmountMin'    => [ Vars::UFLOAT, [self::ORDER_AMOUNT_MIN] ],
            'orderAmountMax'    => [ Vars::UFLOAT, [self::ORDER_AMOUNT_MIN] ],
            'orderPriceMin'     => [ Vars::UFLOAT, [self::ORDER_PRICE_MIN] ],
            'orderPriceMax'     => [ Vars::UFLOAT, [self::ORDER_PRICE_MIN] ],
            'enabled'           => [ Vars::BOOL ],
            'created'           => [ Vars::DATETIME ],
            'updated'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getPairsWithRelated(int $limit): array
    {
        $cols = [];
        foreach (array_keys($this->_fields) as $colName) {
            $cols[] = "ecp.$colName";
        }
        $cols[] = 'cp.code AS __currPairCode';

        $rows = $this->query(
            "SELECT %cols%
                FROM $this->_table AS ecp
                LEFT JOIN " . ModelCurrenciesPairs::inst()->table() . " AS cp
                ON ecp.currPairId = cp.id
                ORDER BY ecp.id ASC
                LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
        if (!$rows) {
            return [];
        }

        foreach ($rows as &$row) {
            $row = ClassF::insertElementIntoArray($row, '__currPairCode', $row['__currPairCode'], ['after', 'currPairId']);
        } unset($row);

        return $rows;
    }

    function getPairsByExchangeId(int $exchangeId, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['%where%' => [
                'exchangeId' => $exchangeId
            ]]
        )->rows();
    }

    function getActivePairsByExchangeId(int $exchangeId, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where% AND enabled = 1
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['%where%' => [
                'exchangeId' => $exchangeId
            ]]
        )->rows();
    }

    function getPairById(int $id, array $cols = ['*']): array
    {
        return $this->_getRowById($id, $cols);
    }

    function getPairByExchangeIdPairId(int $exchangeId, int $currPairId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%",
            ['%cols%' => $cols],
            ['%where%' => [
                'exchangeId' => $exchangeId,
                'currPairId' => $currPairId,
            ]]
        )->row();
    }

    function getActivePairByExchangeIdPairId(int $exchangeId, int $currPairId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where% AND enabled = 1",
            ['%cols%' => $cols],
            ['%where%' => [
                'exchangeId' => $exchangeId,
                'currPairId' => $currPairId,
            ]]
        )->row();
    }

    function insert(
        int $exchangeId,
        int $currPairId,
        float $orderAmountMin,
        float $orderAmountMax,
        float $orderPriceMin,
        float $orderPriceMax
    ): int
    {
        if ($orderAmountMax < $orderAmountMin) {
            throw new Err("Bad order amount max [$orderAmountMax < $orderAmountMin]");
        }
        if ($orderPriceMax < $orderPriceMin) {
            throw new Err("Bad order price max [$orderPriceMax < $orderPriceMin]");
        }
        return $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'exchangeId'        => $exchangeId,
                'currPairId'        => $currPairId,
                'orderAmountMin'    => $orderAmountMin,
                'orderAmountMax'    => $orderAmountMax,
                'orderPriceMin'     => $orderPriceMin,
                'orderPriceMax'     => $orderPriceMax,
            ])]
        )->exec()->lastId();
    }

    function enable(int $id)
    {
        $this->_enableRowById($id);
    }

    function disable(int $id)
    {
        $this->_disableRowById($id);
    }
}