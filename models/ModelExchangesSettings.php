<?php
/**
 *
 */
class ModelExchangesSettings extends Model
{
    // Amount of funds that can be used as an asset in margin trade
    // 0 - Margin is disabled
    // 1.00 - Means 1-to-1. I.e. asset is equal to 100% of funds total amount
    // Max is 3.33 (at Bitfinex)
    const MARGIN_TRADE_ASSET_DEFAULT = 1.00;

    protected $_table = 'exchangesSettings';

    function __construct()
    {
        $this->_fields = [
            'exchangeId'        => [ Vars::UINT, [1] ],
            'marginTradeAsset'  => [ Vars::UFLOAT, [0, 3.33] ],
            'created'           => [ Vars::DATETIME ],
            'updated'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getSettingsRows(int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            ORDER BY exchangeId ASC 
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getSettingsRowByExchangeId(int $exchangeId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%",
            ['%cols%' => $cols],
            ['%where%' => [
                'exchangeId' => $exchangeId,
            ]]
        )->row();
    }

    function insert(int $exchangeId, float $marginTradeAsset)
    {
        $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'exchangeId' => $exchangeId,
                'marginTradeAsset' => $marginTradeAsset,
            ])]
        )->exec();
    }

    function update(int $exchangeId, float $marginTradeAsset)
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set%
            WHERE %where%",
            ['%set%' => $this->filter([
                'marginTradeAsset' => $marginTradeAsset,
            ])],
            ['%where%' => [
                'exchangeId' => $exchangeId,
            ]]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }
}