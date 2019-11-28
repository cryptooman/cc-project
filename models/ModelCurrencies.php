<?php
/**
 *
 */
class ModelCurrencies extends Model
{
    const USD_ID = 1;
    const USD_CODE = 'USD';

    protected $_table = 'currencies';

    function __construct()
    {
        $this->_fields = [
            'id'        => [ Vars::UINT, [1] ],
            'code'      => [ Vars::REGX, ['!^[A-Z]{3,5}$!'] ],
            'name'      => [ Vars::MBSTR, [1, 20] ],
            'isCrypto'  => [ Vars::BOOL ],
            'enabled'   => [ Vars::BOOL ],
            'created'   => [ Vars::DATETIME ],
            'updated'   => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getCurrencies(int $limit, array $cols = ['*']): array
    {
        return $this->_getRows($limit, $cols);
    }

    function getCurrencyById(int $id, array $cols = ['*']): array
    {
        return $this->_getRowById($id, $cols);
    }

    function getActiveCurrencyById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE id = :id AND enabled = 1",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    function getCurrencyByCode(string $code, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE code = :code",
            ['%cols%' => $cols],
            ['code' => $code]
        )->row();
    }

    function getActiveCurrencyByCode(string $code, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE code = :code AND enabled = 1",
            ['%cols%' => $cols],
            ['code' => $code]
        )->row();
    }

    function idToCode(int $id, bool $exception = true): string
    {
        $code = $this->_getCachedCodeById($id);
        if (!$code) {
            if ($exception) {
                throw new Err("No code for currency id [$id]");
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
                throw new Err("No id for currency code [$code]");
            }
            return 0;
        }
        return $id;
    }

    function insert(string $code, string $name, bool $isCrypto): int
    {
        return $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'code'      => $code,
                'name'      => $name,
                'isCrypto'  => (int) $isCrypto,
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

    private function _getCachedCodeById(int $id): string
    {
        return Cache::make(
            [__CLASS__, __FUNCTION__, $id],
            function() use($id) {
                $curr = ModelCurrencies::inst()->getCurrencyById($id, ['code']);
                if (!$curr) {
                    return '';
                }
                return $curr['code'];
            },
            Cache::EXPIRE_MINUTE
        );
    }

    private function _getCachedIdByCode(string $code): int
    {
        return Cache::make(
            [__CLASS__, __FUNCTION__, $code],
            function() use($code) {
                $curr = ModelCurrencies::inst()->getCurrencyByCode($code, ['id']);
                if (!$curr) {
                    return 0;
                }
                return $curr['id'];
            },
            Cache::EXPIRE_MINUTE
        );
    }
}