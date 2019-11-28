<?php
/**
 *
 */
abstract class ModelAbstractAdminsOrders extends Model
{
    const TYPE_NEW       = 'new';
    const TYPE_REPLACE   = 'replace';
    const TYPE_CANCEL    = 'cancel';

    const PRIORITY_TYPE_NEW     = 0;
    const PRIORITY_TYPE_REPLACE = 10;
    const PRIORITY_TYPE_CANCEL  = 20;

    const STATUS_NEW            = 'new';
    const STATUS_DOING          = 'doing';
    const STATUS_COMPLETED      = 'completed';
    const STATUS_REJECTED       = 'rejected';
    const STATUS_FAILED         = 'failed';
    const STATUS_SPECIAL        = 'special';

    const STATUS_CODE_NEW                           = 0;
    const STATUS_CODE_DOING                         = 100;
    const STATUS_CODE_DOING_DECOMPOSE               = 101;
    const STATUS_CODE_DOING_WAIT_APPROVE            = 102;
    const STATUS_CODE_DOING_APPROVED                = 103;
    const STATUS_CODE_DOING_CREATE_BUILD_REQ        = 104; // Building requests
    const STATUS_CODE_DOING_CREATE_WAIT_REQ         = 105; // Waiting requests to complete
    const STATUS_CODE_DOING_CREATED                 = 106;
    const STATUS_CODE_DOING_CANCELLED               = 107;
    const STATUS_CODE_DOING_STATE_BUILD_REQ         = 108;
    const STATUS_CODE_DOING_STATE_WAIT_REQ          = 109;
    const STATUS_CODE_COMPLETED                     = 200;
    const STATUS_CODE_REJECTED                      = 300;
    const STATUS_CODE_REJECTED_DISAPPROVED          = 301;
    const STATUS_CODE_REJECTED_REPLACED             = 302;
    const STATUS_CODE_REJECTED_CANCELLED            = 303;
    const STATUS_CODE_FAILED                        = 400;
    const STATUS_CODE_FAILED_NO_ACCOUNTS_SELECTED   = 401;
    const STATUS_CODE_FAILED_NO_DECOMPOSED_ORDERS   = 402;
    const STATUS_CODE_SPECIAL                       = 1000;

    const COMPLEXITY_TYPE1  = 'type1';
    const COMPLEXITY_TYPE2  = 'type2';

    const SHARE_MIN     = Cnst::UFLOAT_MIN_NON_ZERO;
    const SHARE_MAX     = 1.0;
    const AMOUNT_MIN    = Cnst::UFLOAT_MIN_NON_ZERO;
    const PRICE_MIN     = Cnst::UFLOAT_MIN_NON_ZERO;

    const SIDE_BUY = 'buy';
    const SIDE_SELL = 'sell';

    const EXEC_STOP = 'stop';
    const EXEC_LIMIT = 'limit';
    const EXEC_MARKET = 'market';

    static function getTypes(): array
    {
        return [
            self::TYPE_NEW,
            self::TYPE_REPLACE,
            self::TYPE_CANCEL,
        ];
    }

    static function getStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_DOING,
            self::STATUS_COMPLETED,
            self::STATUS_REJECTED,
            self::STATUS_FAILED,
            self::STATUS_SPECIAL,
        ];
    }

    static function getActiveStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_DOING
        ];
    }

    static function getStatusCodes(): array
    {
        return array_merge(
            self::getStatusCodesNew(),
            self::getStatusCodesDoing(),
            self::getStatusCodesCompleted(),
            self::getStatusCodesRejected(),
            self::getStatusCodesFailed(),
            self::getStatusCodesSpecial()
        );
    }

    static function getStatusCodesNew(): array
    {
        return [
            self::STATUS_CODE_NEW,
        ];
    }

    static function getStatusCodesDoing(): array
    {
        return [
            self::STATUS_CODE_DOING,
            self::STATUS_CODE_DOING_DECOMPOSE,
            self::STATUS_CODE_DOING_WAIT_APPROVE,
            self::STATUS_CODE_DOING_APPROVED,
            self::STATUS_CODE_DOING_CREATE_BUILD_REQ,
            self::STATUS_CODE_DOING_CREATE_WAIT_REQ,
            self::STATUS_CODE_DOING_CREATED,
            self::STATUS_CODE_DOING_CANCELLED,
            self::STATUS_CODE_DOING_STATE_BUILD_REQ,
            self::STATUS_CODE_DOING_STATE_WAIT_REQ,
        ];
    }

    static function getStatusCodesCompleted(): array
    {
        return [
            self::STATUS_CODE_COMPLETED,
        ];
    }

    static function getStatusCodesRejected(): array
    {
        return [
            self::STATUS_CODE_REJECTED,
            self::STATUS_CODE_REJECTED_DISAPPROVED,
            self::STATUS_CODE_REJECTED_REPLACED,
            self::STATUS_CODE_REJECTED_CANCELLED,
        ];
    }

    static function getStatusCodesFailed(): array
    {
        return [
            self::STATUS_CODE_FAILED,
            self::STATUS_CODE_FAILED_NO_ACCOUNTS_SELECTED,
            self::STATUS_CODE_FAILED_NO_DECOMPOSED_ORDERS,
        ];
    }

    static function getStatusCodesSpecial(): array
    {
        return [
            self::STATUS_CODE_SPECIAL,
        ];
    }

    static function getActiveStatusCodes(): array
    {
        return array_merge(
            self::getStatusCodesNew(),
            self::getStatusCodesDoing()
        );
    }

    static function verboseStatusCode(int $code): string
    {
        $verbose = [
            self::STATUS_CODE_NEW                           => 'new',
            self::STATUS_CODE_DOING                         => 'doing',
            self::STATUS_CODE_DOING_DECOMPOSE               => 'doingDecompose',
            self::STATUS_CODE_DOING_WAIT_APPROVE            => 'doingWaitApprove',
            self::STATUS_CODE_DOING_APPROVED                => 'doingApproved',
            self::STATUS_CODE_DOING_CREATE_BUILD_REQ        => 'doingCreateBuildRequest',
            self::STATUS_CODE_DOING_CREATE_WAIT_REQ         => 'doingCreateWaitRequest',
            self::STATUS_CODE_DOING_CREATED                 => 'doingCreated',
            self::STATUS_CODE_DOING_CANCELLED               => 'doingCreated',
            self::STATUS_CODE_DOING_STATE_BUILD_REQ         => 'doingStateBuildRequest',
            self::STATUS_CODE_DOING_STATE_WAIT_REQ          => 'doingStateWaitRequest',
            self::STATUS_CODE_COMPLETED                     => 'completed',
            self::STATUS_CODE_REJECTED                      => 'rejected',
            self::STATUS_CODE_REJECTED_DISAPPROVED          => 'rejectedDisapproved',
            self::STATUS_CODE_REJECTED_REPLACED             => 'rejectedReplaced',
            self::STATUS_CODE_REJECTED_CANCELLED            => 'rejectedCancelled',
            self::STATUS_CODE_FAILED                        => 'failed',
            self::STATUS_CODE_FAILED_NO_ACCOUNTS_SELECTED   => 'noAccountsSelected',
            self::STATUS_CODE_FAILED_NO_DECOMPOSED_ORDERS   => 'noDecomposedOrders',
            self::STATUS_CODE_SPECIAL                       => 'special',
        ];
        if (!isset($verbose[$code])) {
            throw new Err("Bad code [$code] to verbose");
        }
        return $verbose[$code];
    }

    static function getComplexities(): array
    {
        return [
            self::COMPLEXITY_TYPE1,
            self::COMPLEXITY_TYPE2,
        ];
    }

    static function getAmountMultipliers()
    {
        return ['0', '1', '2'];
    }

    static function getSides()
    {
        return [
            self::SIDE_BUY,
            self::SIDE_SELL,
        ];
    }

    static function getExecs()
    {
        return [
            self::EXEC_STOP,
            self::EXEC_LIMIT,
            self::EXEC_MARKET,
        ];
    }

    protected function _isStatusActive(string $status, bool $exception = true): bool
    {
        if (!in_array($status, self::getActiveStatuses())) {
            if ($exception) {
                throw new Err("Status [$status] is inactive");
            }
            return false;
        }
        return true;
    }

    protected function _isStatusCodeActive(int $statusCode, bool $exception = true): bool
    {
        if (!in_array($statusCode, self::getActiveStatusCodes())) {
            if ($exception) {
                throw new Err("Status code [$statusCode] is inactive");
            }
            return false;
        }
        return true;
    }

    protected function _getActiveStatusesIn(): string
    {
        $quoted = [];
        foreach (self::getActiveStatuses() as $status) {
            $quoted[] = $this->quoteValue($status);
        }
        return join(', ', $quoted);
    }

    protected function _getActiveStatusCodesIn(): string
    {
        $quoted = [];
        foreach (self::getActiveStatusCodes() as $statusCode) {
            $quoted[] = $this->quoteValue($statusCode);
        }
        return join(', ', $quoted);
    }
}