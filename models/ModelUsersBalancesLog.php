<?php
/**
 *
 */
class ModelUsersBalancesLog extends ModelAbstractBalancesLog
{
    const ENTITY = self::ENTITY_USER;

    protected $_table = 'usersBalancesLog';

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UBIGINT, [1] ],
            'balanceId'         => [ Vars::UINT, [1] ],
            'userId'            => [ Vars::UINT, [1] ],
            'apiKeyId'          => [ Vars::UINT, [1] ],
            'currencyId'        => [ Vars::UINT, [1] ],
            'type'              => [ Vars::ENUM, self::getTypes() ],
            'operationCode'     => [ Vars::ENUM, self::getOperationCodes() ],
            'amount'            => [ Vars::FLOAT, [self::AMOUNT_MIN] ],
            'amountInUsd'       => [ Vars::FLOAT, [self::AMOUNT_IN_USD_MIN] ],
            'available'         => [ Vars::FLOAT, [self::AVAILABLE_MIN] ],
            'availableInUsd'    => [ Vars::FLOAT, [self::AVAILABLE_IN_USD_MIN] ],
            'hold'              => [ Vars::FLOAT, [self::HOLD_MIN] ],
            'holdInUsd'         => [ Vars::FLOAT, [self::HOLD_IN_USD_MIN] ],
            'created'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getLatestRowsWithRelated(int $limit): array
    {
        $cols = [];
        foreach (array_keys($this->_fields) as $colName) {
            $cols[] = "b.$colName";
        }
        $cols[] = 'c.code AS currCode';
        $cols[] = 'k.name AS apiKeyName';

        return $this->query(
            "SELECT %cols%
            FROM $this->_table AS b
            LEFT JOIN " . ModelUsersApiKeys::inst()->table() . " AS k
            ON b.apiKeyId = k.id            
            LEFT JOIN " . ModelCurrencies::inst()->table() . " AS c
            ON b.currencyId = c.id
            ORDER BY b.id DESC
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function insert(
        int $balanceId,
        int $userId,
        int $apiKeyId,
        int $currencyId,
        string $type,
        int $operationCode,
        float $amount,
        float $amountInUsd,
        float $available,
        float $availableInUsd,
        float $hold,
        float $holdInUsd
    ): int
    {
        return $this->_insert([
            'balanceId'         => $balanceId,
            'userId'            => $userId,
            'apiKeyId'          => $apiKeyId,
            'currencyId'        => $currencyId,
            'type'              => $type,
            'operationCode'     => $operationCode,
            'amount'            => NumFloat::floor($amount),
            'amountInUsd'       => NumFloat::floor($amountInUsd),
            'available'         => NumFloat::floor($available),
            'availableInUsd'    => NumFloat::floor($availableInUsd),
            'hold'              => NumFloat::floor($hold),
            'holdInUsd'         => NumFloat::floor($holdInUsd),
        ]);
    }
}