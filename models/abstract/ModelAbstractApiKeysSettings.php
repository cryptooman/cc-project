<?php
/**
 *
 */
abstract class ModelAbstractApiKeysSettings extends Model
{
    const ORDER_BALANCE_SHARE_MIN     = 0;
    const ORDER_BALANCE_SHARE_MAX     = 2.0;
    const ORDER_BALANCE_SHARE_DEFAULT = 0.9;

    function getSettingsRowByKeyId(int $apiKeyId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE %where%",
            ['%cols%' => $cols],
            ['%where%' => [
                'apiKeyId' => $apiKeyId,
            ]]
        )->row();
    }

    function insert(int $apiKeyId, float $orderBalanceShareMax = self::ORDER_BALANCE_SHARE_DEFAULT)
    {
        $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'apiKeyId'              => $apiKeyId,
                'orderBalanceShareMax'  => $orderBalanceShareMax,
            ])]
        )->exec();
    }

    function update(int $apiKeyId, float $orderBalanceShareMax)
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set%
            WHERE %where%",
            ['%set%' => $this->filter([
                'orderBalanceShareMax' => $orderBalanceShareMax,
            ])],
            ['%where%' => [
                'apiKeyId' => $apiKeyId,
            ]]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }
}