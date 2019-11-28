<?php
/**
 *
 */
class ModelSystemBalances extends ModelAbstractBalances
{
    const ENTITY = self::ENTITY_SYSTEM;

    const REQUESTER_TYPE        = ModelExchangesRequestsStack::REQUESTER_TYPE_SYSTEM_BALANCE;
    const REQUESTER_TYPE_BUY    = ModelExchangesRequestsStack::REQUESTER_TYPE_SYSTEM_BALANCE_BUY;
    const REQUESTER_TYPE_SELL   = ModelExchangesRequestsStack::REQUESTER_TYPE_SYSTEM_BALANCE_SELL;

    protected $_table = 'systemBalances';

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UINT, [1] ],
            'apiKeyId'          => [ Vars::UINT, [1] ],
            'currencyId'        => [ Vars::UINT, [1] ],
            'type'              => [ Vars::ENUM, self::getTypes() ],
            'requestStrId'      => [ Vars::HASH, [40, 40] ],
            'requestGroupStrId' => [ Vars::HASH, [40, 40] ],
            'status'            => [ Vars::ENUM, self::getStatuses() ],
            'statusCode'        => [ Vars::ENUM, self::getStatusCodes() ],
            'statusMsg'         => [ Vars::STR, [0], function($v) { return Str::cutAddDots($v, 2048); } ],
            'amount'            => [ Vars::FLOAT, [self::AMOUNT_MIN] ],
            'amountInUsd'       => [ Vars::FLOAT, [self::AMOUNT_IN_USD_MIN] ],
            'available'         => [ Vars::FLOAT, [self::AVAILABLE_MIN] ],
            'availableInUsd'    => [ Vars::FLOAT, [self::AVAILABLE_IN_USD_MIN] ],
            'hold'              => [ Vars::FLOAT, [self::HOLD_MIN] ],
            'holdInUsd'         => [ Vars::FLOAT, [self::HOLD_IN_USD_MIN] ],
            'syncedAt'          => [ Vars::DATETIME ],
            'enabled'           => [ Vars::BOOL ],
            'created'           => [ Vars::DATETIME ],
            'updated'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getBalancesWithRelated(int $limit): array
    {
        $cols = [];
        foreach (array_keys($this->_fields) as $colName) {
            $cols[] = "b.$colName";
        }
        $cols[] = 'c.code AS currCode';
        //$cols[] = 'c.enabled AS currEnabled';
        $cols[] = 'k.name AS apiKeyName';
        $cols[] = 'k.status AS apiKeyStatus';
        //$cols[] = 'k.enabled AS apiKeyEnabled';

        return $this->query(
            "SELECT %cols%
            FROM $this->_table AS b
            LEFT JOIN " . ModelSystemApiKeys::inst()->table() . " AS k
            ON b.apiKeyId = k.id            
            LEFT JOIN " . ModelCurrencies::inst()->table() . " AS c
            ON b.currencyId = c.id
            ORDER BY b.id ASC
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getBalancesByApiKeyId(int $apiKeyId, int $limit, array $cols = ['*']): array
    {
        return $this->_getBalancesByApiKeyId($apiKeyId, $limit, $cols);
    }

    function getBalancesByApiKeyIdAndType(int $apiKeyId, string $type, int $limit, array $cols = ['*']): array
    {
        return $this->_getBalancesByWhere(['apiKeyId' => $apiKeyId, 'type' => $type], $limit, $cols);
    }

    function getEnabledBalancesByApiKeyId(int $apiKeyId, int $limit, array $cols = ['*']): array
    {
        return $this->_getEnabledBalancesByApiKeyId($apiKeyId, $limit, $cols);
    }

    function getActiveBalancesByApiKeyId(int $apiKeyId, int $limit, array $cols = ['*']): array
    {
        return $this->_getActiveBalancesByApiKeyId($apiKeyId, $limit, $cols);
    }

    function getActiveBalancesByApiKeyIdAndType(int $apiKeyId, string $type, int $limit, array $cols = ['*']): array
    {
        return $this->_getActiveBalancesByApiKeyIdAndType($apiKeyId, $type, $limit, $cols);
    }

    function getBalancesInUsdForOrderDecompose(int $limit): array
    {
        return $this->_getBalancesInUsdForOrderDecompose($limit);
    }

    function getBalanceByApiKeyIdCurrencyIdAndType(int $apiKeyId, int $currencyId, string $type, array $cols = ['*']): array
    {
        return $this->_getBalanceByWhere(
            ['apiKeyId' => $apiKeyId, 'currencyId' => $currencyId, 'type' => $type], $cols
        );
    }

    function getActiveBalanceByApiKeyIdCurrencyIdAndType(int $apiKeyId, int $currencyId, string $type, array $cols = ['*']): array
    {
        return $this->_getActiveBalanceByWhere(
            ['apiKeyId' => $apiKeyId, 'currencyId' => $currencyId, 'type' => $type], $cols
        );
    }

    function getActiveBalancesSumByCurrencyIdAndType(int $currencyId, string $type): array
    {
        return $this->_getActiveBalancesSumByCurrencyIdAndType($currencyId, $type);
    }

    function getEnabledBalancesGroupedStatusesByCurrencyIdAndType(int $currencyId, string $type): array
    {
        return $this->_getEnabledBalancesGroupedStatusesByCurrencyIdAndType($currencyId, $type);
    }

    function createNewBalances(int $apiKeyId)
    {
        $currencies = ModelCurrencies::inst()->getCurrencies(Model::LIMIT_MAX);
        if (!$currencies) {
            throw new Err("No currencies found");
        }
        foreach ($currencies as $currency) {
            $this->createCurrencyNewBalances($apiKeyId, $currency['id']);
        }
    }

    function createCurrencyNewBalances(int $apiKeyId, int $currId)
    {
        $this->beginTransaction();
        foreach (self::getTypes() as $type) {
            $this->insert($apiKeyId, $currId, $type);
        }
        $this->commit();
    }

    function insert(int $apiKeyId, int $currencyId, string $type): int
    {
        $requestStrId = ModelExchangesRequestsStack::makeStrId(self::REQUESTER_TYPE, ClassDateTime::microTime(''));

        if ($type != self::TYPE_POSITION) {
            $requestGroupStrId = ModelExchangesRequestsStack::makeStrId(self::REQUESTER_TYPE_BUY, $apiKeyId);
        }
        else {
            $requestGroupStrId = ModelExchangesRequestsStack::makeStrId(self::REQUESTER_TYPE_SELL, $apiKeyId);
        }

        return $this->_insert([
            'apiKeyId'          => $apiKeyId,
            'currencyId'        => $currencyId,
            'type'              => $type,
            'requestStrId'      => $requestStrId,
            'requestGroupStrId' => $requestGroupStrId,
        ]);
    }

    // NOTE: hold & holdInUsd not used now
    function fundsSync(
        int $apiKeyId,
        int $currencyId,
        string $type,
        float $amount,
        float $available,
        int $operationCode = ModelSystemBalancesLog::OPERATION_CODE_FUNDS_SYNC
    )
    {
        if ($type == self::TYPE_POSITION) {
            // NOTE: Tail fraction of negative "position" balance must be decreased
            $amount = ($amount >= 0) ? NumFloat::floor($amount) : NumFloat::ceil($amount);
            $available = ($available >= 0) ? NumFloat::floor($available) : NumFloat::ceil($available);

            $amountInUsd = ClassCurrency::convertToUsd($amount, $currencyId);
            $availableInUsd = $amountInUsd;

            if ($currencyId == ModelCurrencies::USD_ID && ($amount != 0 || $available != 0)) {
                throw new Err("Position for USD is not allowed: ", func_get_args());
            }
            if (!NumFloat::isEqual($amount, $available)) {
                throw new Err("Bad position: Amount [$amount] is not equal to available [$available]: ", func_get_args());
            }
        }
        else {
            $amount = NumFloat::floor($amount);
            $available = NumFloat::floor($available);

            $amountInUsd = ClassCurrency::convertToUsd($amount, $currencyId);
            $availableInUsd = ClassCurrency::convertToUsd($available, $currencyId);
        }

        $this->_checkApiKey($apiKeyId, $currencyId);

        $balance = $this->getActiveBalanceByApiKeyIdCurrencyIdAndType($apiKeyId, $currencyId, $type);
        if (!$balance) {
            throw new Err("Failed to get balance: ", func_get_args());
        }

        if (
            NumFloat::isEqual($balance['amount'], $amount)
            && NumFloat::isEqual($balance['amountInUsd'], $amountInUsd)
            && NumFloat::isEqual($balance['available'], $available)
            && NumFloat::isEqual($balance['availableInUsd'], $availableInUsd)
        ) {

            $this->setSynced($balance['id']);
            return;
        }

        $this->beginTransaction();

        $this->_fundsSync(
            $amount, $amountInUsd, $available, $availableInUsd, ['apiKeyId' => $apiKeyId, 'currencyId' => $currencyId, 'type' => $type]
        );

        ModelSystemBalancesLog::inst()->insert(
            $balance['id'],
            $apiKeyId,
            $currencyId,
            ModelSystemBalancesLog::TYPE_SYNC,
            $operationCode,
            $amount,
            $amountInUsd,
            $available,
            $availableInUsd,
            $hold = 0,
            $holdInUsd = 0
        );

        $this->setSynced($balance['id']);

        $this->commit();
    }

    function fundsIn(
        int $apiKeyId, int $currencyId, string $type, float $amount, int $operationCode = ModelSystemBalancesLog::OPERATION_CODE_FUNDS_IN
    )
    {
        $amount = NumFloat::floor($amount);

        if ($type == self::TYPE_POSITION) {
            throw new Err("Not allowed for position: ", func_get_args());
        }

        $this->_checkApiKey($apiKeyId, $currencyId);

        $balance = $this->getActiveBalanceByApiKeyIdCurrencyIdAndType($apiKeyId, $currencyId, $type, ['id']);
        if (!$balance) {
            throw new Err("Failed to get balance: ", func_get_args());
        }

        $amountInUsd = ClassCurrency::convertToUsd($amount, $currencyId);

        $this->beginTransaction();

        $this->_fundsIn(
            $amount, $amountInUsd, ['apiKeyId' => $apiKeyId, 'currencyId' => $currencyId, 'type' => $type]
        );

        $balance = $this->getActiveBalanceById($balance['id']);
        if (!$balance) {
            throw new Err("Failed to get balance [%s]", $balance['id']);
        }
        ModelSystemBalancesLog::inst()->insert(
            $balance['id'],
            $apiKeyId,
            $currencyId,
            ModelSystemBalancesLog::TYPE_IN,
            $operationCode,
            $balance['amount'],
            $balance['amountInUsd'],
            $balance['available'],
            $balance['availableInUsd'],
            $balance['hold'],
            $balance['holdInUsd']
        );

        $this->commit();
    }

    function fundsOut(
        int $apiKeyId, int $currencyId, string $type, float $amount, int $operationCode = ModelSystemBalancesLog::OPERATION_CODE_FUNDS_OUT
    )
    {
        $amount = NumFloat::floor($amount);

        if ($type == self::TYPE_POSITION) {
            throw new Err("Not allowed for position: ", func_get_args());
        }

        $this->_checkApiKey($apiKeyId, $currencyId);

        $balance = $this->getActiveBalanceByApiKeyIdCurrencyIdAndType($apiKeyId, $currencyId, $type);
        if (!$balance) {
            throw new Err("Failed to get balance: ", func_get_args());
        }

        $amountInUsd = ClassCurrency::convertToUsd($amount, $currencyId);

        $this->beginTransaction();

        $this->_fundsOut(
            $amount, $amountInUsd, ['apiKeyId' => $apiKeyId, 'currencyId' => $currencyId, 'type' => $type]
        );

        $balance = $this->getActiveBalanceById($balance['id']);
        if (!$balance) {
            throw new Err("Failed to get balance [%s]", $balance['id']);
        }
        ModelSystemBalancesLog::inst()->insert(
            $balance['id'],
            $apiKeyId,
            $currencyId,
            ModelSystemBalancesLog::TYPE_OUT,
            $operationCode,
            $balance['amount'],
            $balance['amountInUsd'],
            $balance['available'],
            $balance['availableInUsd'],
            $balance['hold'],
            $balance['holdInUsd']
        );

        $this->commit();
    }

    function updateStatus(int $id, string $status, int $statusCode, string $statusMsg = '')
    {
        $this->_updateStatus($id, $status, $statusCode, $statusMsg);
    }

    function updateStatusesByApiKeyId(int $apiKeyId, string $status, int $statusCode, string $statusMsg = '')
    {
        $this->_updateStatusesByApiKeyId($apiKeyId, $status, $statusCode, $statusMsg);
    }

    function updateStatusesByApiKeyIdAndType(int $apiKeyId, string $type, string $status, int $statusCode, string $statusMsg = '')
    {
        $this->_updateStatusesByApiKeyIdAndType($apiKeyId, $type, $status, $statusCode, $statusMsg);
    }

    private function _checkApiKey(int $apiKeyId, int $currencyId)
    {
        if (!$apiKeyId || !$currencyId) {
            throw new Err("Bad input: ", func_get_args());
        }
        if (!ModelSystemApiKeys::inst()->getLiveKeyById($apiKeyId)) {
            throw new Err("Failed to get live api key [$apiKeyId]");
        }
    }
}