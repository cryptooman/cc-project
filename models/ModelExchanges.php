<?php
/**
 *
 */
class ModelExchanges extends Model
{
    const BITFINEX_ID = 1;

    protected $_table = 'exchanges';

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UINT, [1] ],
            'name'          => [ Vars::MBSTR, [1, 20] ],
            'siteUrl'       => [ Vars::REGX, ['!^https?://[a-zA-Z\d_\.\-]{5,40}!'], function($v) { return Str::rmEndSlash($v); } ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getExchanges(int $limit, array $cols = ['*']): array
    {
        return $this->_getRows($limit, $cols);
    }

    function getActiveExchanges(int $limit, array $cols = ['*']): array
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

    function getExchangeById(int $id, array $cols = ['*']): array
    {
        return $this->_getRowById($id, $cols);
    }

    function getActiveExchangeById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE id = :id AND enabled = 1",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    function idToName(int $id, bool $exception = true): string
    {
        $name = $this->_getCachedNameById($id);
        if (!$name) {
            if ($exception) {
                throw new Err("No name for exchange id [$id]");
            }
            return '';
        }
        return $name;
    }

    function insert(string $name, string $siteUrl, float $marginTradeAsset = ModelExchangesSettings::MARGIN_TRADE_ASSET_DEFAULT): int
    {
        $this->beginTransaction();

        $exchangeId = $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'name'      => $name,
                'siteUrl'   => $siteUrl,
            ])]
        )->exec()->lastId();

        ModelExchangesSettings::inst()->insert($exchangeId, $marginTradeAsset);

        $this->commit();

        return $exchangeId;
    }

    function enable(int $id)
    {
        $this->_enableRowById($id);
    }

    function disable(int $id)
    {
        $this->_disableRowById($id);
    }

    private function _getCachedNameById(int $id): string
    {
        $code = Cache::make(
            [__CLASS__, __FUNCTION__, $id],
            function() use($id) {
                $curr = ModelExchanges::inst()->getExchangeById($id, ['name']);
                if (!$curr) {
                    return '';
                }
                return $curr['name'];
            },
            Cache::EXPIRE_MINUTE
        );
        return $code;
    }
}