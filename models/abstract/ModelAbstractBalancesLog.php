<?php
/**
 *
 */
abstract class ModelAbstractBalancesLog extends Model
{
    const ENTITY_SYSTEM = ModelAbstractBalances::ENTITY_SYSTEM;
    const ENTITY_USER   = ModelAbstractBalances::ENTITY_USER;
    const ENTITY_TOTAL  = ModelAbstractBalances::ENTITY_TOTAL;

    const TYPE_SYNC     = 'sync';
    const TYPE_IN       = 'in';
    const TYPE_OUT      = 'out';
    const TYPE_HOLD     = 'hold';
    const TYPE_UNHOLD   = 'unhold';

    const OPERATION_CODE_FUNDS_SYNC     = 100;
    const OPERATION_CODE_FUNDS_IN       = 200;
    const OPERATION_CODE_FUNDS_OUT      = 300;
    const OPERATION_CODE_HOLD           = 300;
    const OPERATION_CODE_UNHOLD         = 400;

    const AMOUNT_MIN            = ModelAbstractBalances::AMOUNT_MIN;
    const AMOUNT_IN_USD_MIN     = ModelAbstractBalances::AMOUNT_IN_USD_MIN;
    const AVAILABLE_MIN         = ModelAbstractBalances::AVAILABLE_MIN;
    const AVAILABLE_IN_USD_MIN  = ModelAbstractBalances::AVAILABLE_IN_USD_MIN;
    const HOLD_MIN              = ModelAbstractBalances::HOLD_MIN;
    const HOLD_IN_USD_MIN       = ModelAbstractBalances::HOLD_IN_USD_MIN;

    static function getTypes(): array
    {
        return [
            self::TYPE_SYNC,
            self::TYPE_IN,
            self::TYPE_OUT,
            self::TYPE_HOLD,
            self::TYPE_UNHOLD,
        ];
    }

    static function getOperationCodes(): array
    {
        return [
            self::OPERATION_CODE_FUNDS_SYNC,
            self::OPERATION_CODE_FUNDS_IN,
            self::OPERATION_CODE_FUNDS_OUT,
            self::OPERATION_CODE_HOLD,
            self::OPERATION_CODE_UNHOLD,
        ];
    }

    static function getModel(int $systemBalanceLogId, int $userBalanceLogId): ModelAbstractBalancesLog
    {
        self::checkIds($systemBalanceLogId, $userBalanceLogId);
        if ($systemBalanceLogId) {
            return ModelSystemBalancesLog::inst();
        }
        elseif ($userBalanceLogId) {
            return ModelUsersBalancesLog::inst();
        }
    }

    static function getKeyId(int $systemBalanceLogId, int $userBalanceLogId): int
    {
        self::checkIds($systemBalanceLogId, $userBalanceLogId);
        if ($systemBalanceLogId) {
            return $systemBalanceLogId;
        }
        elseif ($userBalanceLogId) {
            return $userBalanceLogId;
        }
    }

    static function checkIds(int $systemBalanceLogId, int $userBalanceLogId, bool $exception = true): bool
    {
        if (!$systemBalanceLogId && !$userBalanceLogId) {
            if ($exception) {
                throw new Err("Bad balance log id: system [$systemBalanceLogId] user [$userBalanceLogId]");
            }
            return false;
        }
        elseif ($systemBalanceLogId && $userBalanceLogId) {
            if ($exception) {
                throw new Err("Bad balance log id: system [$systemBalanceLogId] user [$userBalanceLogId]");
            }
            return false;
        }
        return true;
    }

    protected function _insert(array $data): int
    {
        return $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter($data)]
        )->exec()->lastId();
    }
}