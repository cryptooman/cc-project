<?php
/**
 *
 */
abstract class ModelAbstractBalances extends Model
{
    const ENTITY_SYSTEM     = ModelAbstractApiKeys::ENTITY_SYSTEM;
    const ENTITY_USER       = ModelAbstractApiKeys::ENTITY_USER;
    const ENTITY_TOTAL      = 'total';

    const TYPE_TRADING      = 'trading';
    const TYPE_POSITION     = 'position';
    const TYPE_DEPOSIT      = 'deposit';
    const TYPE_EXCHANGE     = 'exchange';

    const POSITION_BUY      = 'buy';
    const POSITION_SELL     = 'sell';

    const STATUS_NEW        = 'new';
    const STATUS_SYNCING    = 'syncing';
    const STATUS_SYNCED     = 'synced';
    const STATUS_FAILED     = 'failed';
    const STATUS_SPECIAL    = 'special';

    const STATUS_CODE_NEW                       = 0;
    const STATUS_CODE_SYNCING                   = 100;
    const STATUS_CODE_SYNCED                    = 200;
    const STATUS_CODE_SYNCED_SOME_HAS_FAILED    = 201; // Some composing balances have "failed" status
    const STATUS_CODE_SYNCED_SOME_HAS_NEW       = 202; // Some composing balances have "new" status
    const STATUS_CODE_FAILED                    = 300;
    const STATUS_CODE_FAILED_BAD_API_KEY        = 301;
    const STATUS_CODE_FAILED_UNALIVE_API_KEY    = 302;
    const STATUS_CODE_FAILED_DUPLICATE_API_KEY  = 303;
    const STATUS_CODE_FAILED_INACTIVE_USER      = 304;
    const STATUS_CODE_SPECIAL                   = 1000;

    const AMOUNT_MIN            = Cnst::INT32_MIN;
    const AMOUNT_IN_USD_MIN     = Cnst::INT32_MIN;
    const AVAILABLE_MIN         = Cnst::INT32_MIN;
    const AVAILABLE_IN_USD_MIN  = Cnst::INT32_MIN;
    const HOLD_MIN              = 0;
    const HOLD_IN_USD_MIN       = 0;

    static function getTypes(): array
    {
        return [
            self::TYPE_TRADING,
            self::TYPE_POSITION,
            self::TYPE_DEPOSIT,
            self::TYPE_EXCHANGE,
        ];
    }

    static function getStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_SYNCING,
            self::STATUS_SYNCED,
            self::STATUS_FAILED,
            self::STATUS_SPECIAL,
        ];
    }

    static function getStatusCodes(): array
    {
        return [
            self::STATUS_CODE_NEW,
            self::STATUS_CODE_SYNCING,
            self::STATUS_CODE_SYNCED,
            self::STATUS_CODE_SYNCED_SOME_HAS_FAILED,
            self::STATUS_CODE_SYNCED_SOME_HAS_NEW,
            self::STATUS_CODE_FAILED,
            self::STATUS_CODE_FAILED_BAD_API_KEY,
            self::STATUS_CODE_FAILED_UNALIVE_API_KEY,
            self::STATUS_CODE_FAILED_DUPLICATE_API_KEY,
            self::STATUS_CODE_FAILED_INACTIVE_USER,
            self::STATUS_CODE_SPECIAL,
        ];
    }

    static function getModel(int $systemBalanceId, int $userBalanceId): ModelAbstractBalances
    {
        self::checkIds($systemBalanceId, $userBalanceId);
        if ($systemBalanceId) {
            return ModelSystemBalances::inst();
        }
        elseif ($userBalanceId) {
            return ModelUsersBalances::inst();
        }
    }

    static function getKeyId(int $systemBalanceId, int $userBalanceId): int
    {
        self::checkIds($systemBalanceId, $userBalanceId);
        if ($systemBalanceId) {
            return $systemBalanceId;
        }
        elseif ($userBalanceId) {
            return $userBalanceId;
        }
    }

    static function checkIds(int $systemBalanceId, int $userBalanceId, bool $exception = true): bool
    {
        if (!$systemBalanceId && !$userBalanceId) {
            if ($exception) {
                throw new Err("Bad balance id: system [$systemBalanceId] user [$userBalanceId]");
            }
            return false;
        }
        elseif ($systemBalanceId && $userBalanceId) {
            if ($exception) {
                throw new Err("Bad balance id: system [$systemBalanceId] user [$userBalanceId]");
            }
            return false;
        }
        return true;
    }

    function getBalances(int $limit, array $cols = ['*']): array
    {
        return $this->_getRows($limit, $cols);
    }

    function getBalancesByTypes(array $types, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE type IN(" . $this->_quoteIn($types) . ") 
            ORDER BY id ASC                 
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getBalanceById(int $id, array $cols = ['*']): array
    {
        return $this->_getBalanceByWhere(['id' => $id], $cols);
    }

    function getActiveBalanceById(int $id, array $cols = ['*']): array
    {
        return $this->_getActiveBalanceByWhere(['id' => $id], $cols);
    }

    function isActiveBalancesExistsByApiKeyId(int $apiKeyId): bool
    {
        return (bool) $this->query(
            "SELECT COUNT(*) AS c 
            FROM $this->_table 
            WHERE %where% 
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ")
                  AND enabled = 1",
            ['%where%' => [
                'apiKeyId' => $apiKeyId
            ]]
        )->value();
    }

    function isActiveBalancesExistsByApiKeyIdAndType(int $apiKeyId, string $type): bool
    {
        return (bool) $this->query(
            "SELECT COUNT(*) AS c 
            FROM $this->_table 
            WHERE %where% 
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ")
                  AND enabled = 1",
            ['%where%' => [
                'apiKeyId' => $apiKeyId,
                'type' => $type,
            ]]
        )->value();
    }

    function setSynced(int $id)
    {
        $this->query(
            "UPDATE $this->_table 
            SET syncedAt = NOW()
            WHERE id = :id AND enabled = 1",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ANY);
    }

    protected function _getBalancesByApiKeyId(int $apiKeyId, int $limit, array $cols = ['*']): array
    {
        return $this->_getBalancesByWhere(['apiKeyId' => $apiKeyId], $limit, $cols);
    }

    protected function _getEnabledBalancesByApiKeyId(int $apiKeyId, int $limit, array $cols = ['*']): array
    {
        return $this->_getBalancesByWhere(
            ['apiKeyId' => $apiKeyId, 'enabled' => 1], $limit, $cols
        );
    }

    protected function _getActiveBalancesByApiKeyId(int $apiKeyId, int $limit, array $cols = ['*']): array
    {
        return $this->_getActiveBalancesByWhere(['apiKeyId' => $apiKeyId], $limit, $cols);
    }

    protected function _getActiveBalancesByApiKeyIdAndType(int $apiKeyId, string $type, int $limit, array $cols = ['*']): array
    {
        return $this->_getActiveBalancesByWhere(
            ['apiKeyId' => $apiKeyId, 'type' => $type], $limit, $cols
        );
    }

    protected function _getBalancesInUsdForOrderDecompose(int $limit): array
    {
        $tradings = $this->query(
            "SELECT
                apiKeyId,
                type,
                SUM(amountInUsd) AS amountInUsdSum
            FROM $this->_table 
            WHERE %where%
                  AND amountInUsd > 0
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ") 
                  AND enabled = 1
            GROUP BY apiKeyId
            ORDER BY apiKeyId ASC
            LIMIT $limit",
            ['%where%' => [
                'type' => self::TYPE_TRADING
            ]]
        )->rows();

        $positionsBuy = $this->query(
            "SELECT
                apiKeyId,
                type,
                '" . self::POSITION_BUY . "',
                SUM(amountInUsd) AS amountInUsdSum
            FROM $this->_table 
            WHERE %where%
                  AND amountInUsd > 0
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ") 
                  AND enabled = 1
            GROUP BY apiKeyId
            ORDER BY apiKeyId ASC
            LIMIT $limit",
            ['%where%' => [
                'type' => self::TYPE_POSITION
            ]]
        )->rows();

        $positionsSell = $this->query(
            "SELECT
                apiKeyId,
                type,
                '" . self::POSITION_SELL . "',
                SUM(amountInUsd) AS amountInUsdSum
            FROM $this->_table 
            WHERE %where%
                  AND amountInUsd < 0
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ") 
                  AND enabled = 1
            GROUP BY apiKeyId
            ORDER BY apiKeyId ASC
            LIMIT $limit",
            ['%where%' => [
                'type' => self::TYPE_POSITION
            ]]
        )->rows();

        $res = [];
        foreach (array_merge($tradings, $positionsBuy, $positionsSell) as $sum) {
            if (!isset($res[$sum['apiKeyId']])) {
                $res[$sum['apiKeyId']] = [
                    'apiKeyId'              => $sum['apiKeyId'],
                    self::TYPE_TRADING      => ['amountInUsdSum' => 0],
                    self::TYPE_POSITION     => ['amountInUsdSum' => 0],
                ];
            }
            if ($sum['type'] == self::TYPE_TRADING) {
                $res[$sum['apiKeyId']][self::TYPE_TRADING]['amountInUsdSum'] += $sum['amountInUsdSum'];
            }
            elseif ($sum['type'] == self::TYPE_POSITION && !empty($sum[self::POSITION_BUY])) {
                $res[$sum['apiKeyId']][self::TYPE_POSITION]['amountInUsdSum'] += $sum['amountInUsdSum'];
            }
            elseif ($sum['type'] == self::TYPE_POSITION && !empty($sum[self::POSITION_SELL])) {
                $res[$sum['apiKeyId']][self::TYPE_POSITION]['amountInUsdSum'] += abs($sum['amountInUsdSum']);
            }
            else {
                throw new Err("Bad condition");
            }
        }
        return $res;
    }

    protected function _getBalancesByWhere(array $where, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where% 
            ORDER BY id ASC                 
            LIMIT $limit",
            ['%cols%' => $cols],
            ['%where%' => $where]
        )->rows();
    }

    protected function _getActiveBalancesByWhere(array $where, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ") 
                  AND enabled = 1
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['%where%' => $where]
        )->rows();
    }

    protected function _getActivePositiveBalancesByWhere(array $where, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%
                  AND available > 0
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ") 
                  AND enabled = 1
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['%where%' => $where]
        )->rows();
    }

    protected function _getBalanceByWhere(array $where, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%",
            ['%cols%' => $cols],
            ['%where%' => $where]
        )->row();
    }

    protected function _getActiveBalanceByWhere(array $where, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where% 
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ")
                  AND enabled = 1",
            ['%cols%' => $cols],
            ['%where%' => $where]
        )->row();
    }

    protected function _getActiveBalancesSumByCurrencyIdAndType(int $currencyId, string $type): array
    {
        $res = $this->query(
            "SELECT SUM(amount) AS amount,
                    SUM(amountInUsd) AS amountInUsd,
                    SUM(available) AS available, 
                    SUM(availableInUsd) AS availableInUsd, 
                    SUM(hold) AS hold,
                    SUM(holdInUsd) AS holdInUsd
            FROM $this->_table 
            WHERE %where%
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ")
                  AND enabled = 1",
            ['%where%' => [
                'currencyId' => $currencyId,
                'type' => $type
            ]]
        )->row();

        $res['amount']          = ($res['amount']) ?: 0;
        $res['amountInUsd']     = ($res['amountInUsd']) ?: 0;
        $res['available']       = ($res['available']) ?: 0;
        $res['availableInUsd']  = ($res['availableInUsd']) ?: 0;
        $res['hold']            = ($res['hold']) ?: 0;
        $res['holdInUsd']       = ($res['holdInUsd']) ?: 0;

        return $res;
    }

    protected function _getEnabledBalancesGroupedStatusesByCurrencyIdAndType(int $currencyId, string $type): array
    {
        return $this->query(
            "SELECT status, COUNT(*) AS count
            FROM $this->_table 
            WHERE %where% AND enabled = 1
            GROUP BY status
            ORDER BY id ASC",
            ['%where%' => [
                'currencyId' => $currencyId,
                'type' => $type
            ]]
        )->rows();
    }

    protected function _insert(array $data): int
    {
        return $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter($data)]
        )->exec()->lastId();
    }

    // NOTE: hold & holdInUsd not used now
    protected function _fundsSync(float $amount, float $amountInUsd, float $available, float $availableInUsd, array $where)
    {
        if ($available && $available > $amount) {
            throw new Err("Bad available [$available]: ", func_get_args());
        }
        if ($availableInUsd && $availableInUsd > $amountInUsd) {
            throw new Err("Bad availableInUsd [$availableInUsd]: ", func_get_args());
        }
        if ($amount != 0 && $amountInUsd == 0) {
            throw new Err("Bad amountInUsd [$amountInUsd]: ", func_get_args());
        }
        if ($available != 0 && $availableInUsd == 0) {
            throw new Err("Bad availableInUsd [$availableInUsd]: ", func_get_args());
        }
        $this->query(
            "UPDATE $this->_table
            SET %set%
            WHERE %where%
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ") 
                  AND enabled = 1",
            ['%set%' => [
                'amount'         => $this->filterOne('amount', $amount),
                'amountInUsd'    => $this->filterOne('amountInUsd', $amountInUsd),
                'available'      => $this->filterOne('available', $available),
                'availableInUsd' => $this->filterOne('availableInUsd', $availableInUsd),
            ]],
            ['%where%' => $where]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _fundsIn(float $amount, float $amountInUsd, array $where)
    {
        if ($amount <= 0) {
            throw new Err("Bad amount [$amount]");
        }
        if ($amountInUsd <= 0) {
            throw new Err("Bad amountInUsd [$amountInUsd]");
        }
        if ($amount != 0 && $amountInUsd == 0) {
            throw new Err("Bad amountInUsd [$amountInUsd]: ", func_get_args());
        }
        $this->query(
            "UPDATE $this->_table
            SET amount = amount + :amount,
                amountInUsd = amountInUsd + :amountInUsd,
                available = available + :amount,
                availableInUsd = availableInUsd + :amountInUsd
            WHERE %where%
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ") 
                  AND enabled = 1",
            [
                'amount' => $this->filterOne('amount', $amount),
                'amountInUsd' => $this->filterOne('amountInUsd', $amountInUsd),
            ],
            ['%where%' => $where]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _fundsOut(float $amount, float $amountInUsd, array $where)
    {
        if ($amount <= 0) {
            throw new Err("Bad amount [$amount]");
        }
        if ($amountInUsd <= 0) {
            throw new Err("Bad amountInUsd [$amountInUsd]");
        }
        if ($amount != 0 && $amountInUsd == 0) {
            throw new Err("Bad amountInUsd [$amountInUsd]: ", func_get_args());
        }
        $this->query(
            "UPDATE $this->_table
            SET amount = amount - :amount,
                amountInUsd = amountInUsd - :amountInUsd,
                available = available - :amount,
                availableInUsd = availableInUsd - :amountInUsd
            WHERE %where%
                  AND statusCode IN(" . $this->_getActiveStatusCodesIn() . ") 
                  AND enabled = 1",
            [
                'amount' => $this->filterOne('amount', $amount),
                'amountInUsd' => $this->filterOne('amountInUsd', $amountInUsd),
            ],
            ['%where%' => $where]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _updateStatus(int $id, string $status, int $statusCode, string $statusMsg = '')
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set% 
            WHERE id = :id AND enabled = 1",
            ['%set%' => $this->filter([
                'status' => $status,
                'statusCode' => $statusCode,
                'statusMsg' => $statusMsg,
            ])],
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _updateStatusesByApiKeyId(int $apiKeyId, string $status, int $statusCode, string $statusMsg = '')
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set% 
            WHERE apiKeyId = :apiKeyId AND enabled = 1",
            ['%set%' => $this->filter([
                'status' => $status,
                'statusCode' => $statusCode,
                'statusMsg' => $statusMsg,
            ])],
            ['apiKeyId' => $apiKeyId]
        )->exec()->affectedRows(self::AFFECTED_ONE_OR_MORE);
    }

    protected function _updateStatusesByApiKeyIdAndType(int $apiKeyId, string $type, string $status, int $statusCode, string $statusMsg = '')
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set% 
            WHERE %where% AND enabled = 1",
            ['%set%' => $this->filter([
                'status' => $status,
                'statusCode' => $statusCode,
                'statusMsg' => $statusMsg,
            ])],
            ['%where%' => [
                'apiKeyId' => $apiKeyId,
                'type' => $type
            ]]
        )->exec()->affectedRows(self::AFFECTED_ONE_OR_MORE);
    }

    protected function _getActiveStatusCodesIn(): string
    {
        return join(', ', [
            $this->quoteValue(self::STATUS_CODE_NEW),
            $this->quoteValue(self::STATUS_CODE_SYNCING),
            $this->quoteValue(self::STATUS_CODE_SYNCED),
            $this->quoteValue(self::STATUS_CODE_SYNCED_SOME_HAS_FAILED),
            $this->quoteValue(self::STATUS_CODE_SYNCED_SOME_HAS_NEW),
        ]);
    }
}