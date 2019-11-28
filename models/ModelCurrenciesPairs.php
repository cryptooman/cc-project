<?php
/**
 *
 */
class ModelCurrenciesPairs extends Model
{
    protected $_table = 'currenciesPairs';

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UINT, [1] ],
            'code'          => [ Vars::REGX, ['!^[A-Z]{6,10}$!'] ],
            'name'          => [ Vars::MBSTR, [1, 40] ],
            'currency1Id'   => [ Vars::UINT, [1] ],
            'currency2Id'   => [ Vars::UINT, [1] ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getPairs(int $limit, array $cols = ['*']): array
    {
        return $this->_getRows($limit, $cols);
    }

    function getActivePairs(int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE enabled = 1
            ORDER BY id ASC 
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getPairById(int $id, array $cols = ['*']): array
    {
        return $this->_getRowById($id, $cols);
    }

    function getActivePairById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE id = :id AND enabled = 1",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    function getPairByCurrency1Id(int $currency1Id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE currency1Id = :currency1Id",
            ['%cols%' => $cols],
            ['currency1Id' => $currency1Id]
        )->row();
    }

    function getActivePairByCurrency1Id(int $currency1Id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE currency1Id = :currency1Id AND enabled = 1",
            ['%cols%' => $cols],
            ['currency1Id' => $currency1Id]
        )->row();
    }

    function getPairByCode(string $code, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE code = :code",
            ['%cols%' => $cols],
            ['code' => $code]
        )->row();
    }

    function getActivePairByCode(string $code, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE code = :code AND enabled = 1",
            ['%cols%' => $cols],
            ['code' => $code]
        )->row();
    }

    function getPairByCurrency1IdCurrency2Id(int $currency1Id, int $currency2Id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%",
            ['%cols%' => $cols],
            ['%where%' => [
                'currency1Id' => $currency1Id,
                'currency2Id' => $currency2Id,
            ]]
        )->row();
    }

    function idToCode(int $id, bool $exception = true): string
    {
        $code = $this->_getCachedCodeById($id);
        if (!$code) {
            if ($exception) {
                throw new Err("No code for currency pair id [$id]");
            }
            return '';
        }
        return $code;
    }

    function codeToId(string $code, bool $exception = true): int
    {
        $code = strtoupper($code);
        $id = $this->_getCachedIdByCode($code);
        if (!$id) {
            if ($exception) {
                throw new Err("No active id for currency pair code [$code]");
            }
            return 0;
        }
        return $id;
    }

    function insert(string $code, string $name, int $currency1Id, int $currency2Id, int $ordersCount): int
    {
        $this->beginTransaction();

        $currPairId = $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'code'          => $code,
                'name'          => $name,
                'currency1Id'   => $currency1Id,
                'currency2Id'   => $currency2Id,
            ])]
        )->exec()->lastId();

        ModelCurrenciesPairsSettings::inst()->insert($currPairId, $ordersCount);

        $exchanges = ModelExchanges::inst()->getExchanges(Model::LIMIT_MAX, ['id']);
        if (!$exchanges) {
            throw new Err("Failed to get exchanges");
        }
        foreach ($exchanges as $exchange) {
            ModelCurrenciesPairsRatios::inst()->insert($currPairId, $exchange['id']);
        }

        $this->commit();

        return $currPairId;
    }

    function enable(int $id)
    {
        $this->_enableRowById($id);
    }

    function disable(int $id)
    {
        $this->_disableRowById($id);
    }

    private function _getCachedCodeById(int $id): string
    {
        return Cache::make(
            [__CLASS__, __FUNCTION__, $id],
            function() use($id) {
                $currPair = ModelCurrenciesPairs::inst()->getPairById($id, ['code']);
                if (!$currPair) {
                    return '';
                }
                return $currPair['code'];
            },
            Cache::EXPIRE_MINUTE
        );
    }

    private function _getCachedIdByCode(string $code): int
    {
        return Cache::make(
            [__CLASS__, __FUNCTION__, $code],
            function() use($code) {
                $currPair = ModelCurrenciesPairs::inst()->getPairByCode($code, ['id']);
                if (!$currPair) {
                    return '';
                }
                return $currPair['id'];
            },
            Cache::EXPIRE_MINUTE
        );
    }

}