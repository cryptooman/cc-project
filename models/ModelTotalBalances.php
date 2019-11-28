<?php
/**
 *
 */
class ModelTotalBalances extends ModelAbstractBalances
{
    const ENTITY = self::ENTITY_TOTAL;

    protected $_table = 'totalBalances';

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UINT, [1] ],
            'currencyId'        => [ Vars::UINT, [1] ],
            'type'              => [ Vars::ENUM, self::getTypes() ],
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

        return $this->query(
            "SELECT %cols%
            FROM $this->_table AS b
            LEFT JOIN " . ModelCurrencies::inst()->table() . " AS c
            ON b.currencyId = c.id
            ORDER BY b.id ASC
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getEnabledBalanceByCurrencyIdAndType(int $currencyId, string $type, array $cols = ['*']): array
    {
        return $this->_getBalanceByWhere(
            ['currencyId' => $currencyId, 'type' => $type, 'enabled' => 1], $cols
        );
    }

    function insert(int $currencyId, string $type): int
    {
        return $this->_insert([
            'currencyId' => $currencyId,
            'type' => $type,
        ]);
    }

    function createNewBalances()
    {
        $currencies = ModelCurrencies::inst()->getCurrencies(Model::LIMIT_MAX);
        if (!$currencies) {
            throw new Err("No currencies found");
        }
        foreach ($currencies as $currency) {
            $this->createCurrencyNewBalances($currency['id']);
        }
    }

    function createCurrencyNewBalances(int $currId)
    {
        $this->beginTransaction();
        foreach (self::getTypes() as $type) {
            $this->insert($currId, $type);
        }
        $this->commit();
    }

    function syncBalances()
    {
        $currencies = ModelCurrencies::inst()->getCurrencies(Model::LIMIT_MAX);
        if (!$currencies) {
            throw new Err("No currencies found");
        }

        $mSystemBalances = ModelSystemBalances::inst();
        $mUsersBalances = ModelUsersBalances::inst();

        foreach (self::getTypes() as $type) {
            foreach ($currencies as $currency) {
                $systemSum = $mSystemBalances->getActiveBalancesSumByCurrencyIdAndType($currency['id'], $type);
                if (!$systemSum) {
                    throw new Err("Failed to get balances sum [system]: currency [%s] type [$type]", $currency['id']);
                }

                $systemStatuses = $mSystemBalances->getEnabledBalancesGroupedStatusesByCurrencyIdAndType($currency['id'], $type);

                $usersSum = $mUsersBalances->getActiveBalancesSumByCurrencyIdAndType($currency['id'], $type);
                if (!$usersSum) {
                    throw new Err("Failed to get balances sum [users]: currency [%s] type [$type]", $currency['id']);
                }

                $usersStatuses = $mUsersBalances->getEnabledBalancesGroupedStatusesByCurrencyIdAndType($currency['id'], $type);

                list($tbStatus, $tbStatusCode, $tbStatusMsg) = $this->_getSyncedBalanceStatusData($systemStatuses, $usersStatuses);

                $update = [
                    'currencyId'        => $currency['id'],
                    'type'              => $type,
                    'amount'            => $systemSum['amount'] + $usersSum['amount'],
                    'amountInUsd'       => $systemSum['amountInUsd'] + $usersSum['amountInUsd'],
                    'available'         => $systemSum['available'] + $usersSum['available'],
                    'availableInUsd'    => $systemSum['availableInUsd'] + $usersSum['availableInUsd'],
                    'hold'              => $systemSum['hold'] + $usersSum['hold'],
                    'holdInUsd'         => $systemSum['holdInUsd'] + $usersSum['holdInUsd'],
                    'status'            => $tbStatus,
                    'statusCode'        => $tbStatusCode,
                    'statusMsg'         => $tbStatusMsg,
                ];
                $this->_updateSyncedBalance(
                    $update['currencyId'],
                    $update['type'],
                    $update['amount'],
                    $update['amountInUsd'],
                    $update['available'],
                    $update['availableInUsd'],
                    $update['hold'],
                    $update['holdInUsd'],
                    $update['status'],
                    $update['statusCode'],
                    $update['statusMsg']
                );
                Verbose::echo2("Updated total balance with: ", $update);
            }
        }
    }

    private function _getSyncedBalanceStatusData(array $systemStatuses, array $usersStatuses): array
    {
        $statuses = [];
        $statusesTotal = 0;
        foreach (array_merge($systemStatuses, $usersStatuses) as $row) {
            if (!isset($statuses[$row['status']])) {
                $statuses[$row['status']] = 0;
            }
            $statuses[$row['status']] += $row['count'];
            $statusesTotal += $row['count'];
        }

        $tbStatus = '';
        $tbStatusCode = -1;
        $tbStatusMsg = '';

        // If all system and users balances have equal statuses -> Same status for total balance
        $mapStatusToCode = [
            self::STATUS_NEW     => self::STATUS_CODE_NEW,
            self::STATUS_SYNCING => self::STATUS_CODE_SYNCING,
            self::STATUS_SYNCED  => self::STATUS_CODE_SYNCED,
            self::STATUS_FAILED  => self::STATUS_CODE_FAILED,
            // Not used now
            //self::STATUS_SPECIAL => self::STATUS_CODE_SPECIAL,
        ];
        foreach (self::getStatuses() as $status) {
            if (!empty($statuses[$status]) && $statuses[$status] == $statusesTotal) {
                $tbStatus = $status;
                if (!isset($mapStatusToCode[$status])) {
                    throw new Err("Bad status [$status]");
                }
                $tbStatusCode = $mapStatusToCode[$status];
                $tbStatusMsg = "Equal: all composing balances have equal status";

                return [$tbStatus, $tbStatusCode, $tbStatusMsg];
            }
        }

        // If at least one balance is syncing -> Total balance is syncing
        if (!empty($statuses[self::STATUS_SYNCING])) {
            return [self::STATUS_SYNCING, self::STATUS_CODE_SYNCING, "Syncing: some composing balances are syncing"];
        }

        if (!empty($statuses[self::STATUS_SYNCED])) {
            if (!empty($statuses[self::STATUS_FAILED])) {
                return [self::STATUS_SYNCED, self::STATUS_CODE_SYNCED_SOME_HAS_FAILED, "Synced: some composing balances are failed"];
            }
            elseif (!empty($statuses[self::STATUS_NEW])) {
                return [self::STATUS_SYNCED, self::STATUS_CODE_SYNCED_SOME_HAS_NEW, "Synced: some composing balances are new"];
            }
            else {
                throw new Err("Bad condition");
            }
        }

        // NOTE: Do nothing for other cases

        return [$tbStatus, $tbStatusCode, $tbStatusMsg];
    }

    private function _updateSyncedBalance(
        int $currencyId,
        string $type,
        float $amount,
        float $amountInUsd,
        float $available,
        float $availableInUsd,
        float $hold,
        float $holdInUsd,
        string $status = '',
        int $statusCode = -1,
        string $statusMsg = ''
    )
    {
        if ($type == self::TYPE_POSITION) {
            // NOTE: Tail fraction of negative "position" balance must be decreased
            $amount         = ($amount >= 0) ? NumFloat::floor($amount) : NumFloat::ceil($amount);
            $amountInUsd    = ($amountInUsd >= 0) ? NumFloat::floor($amountInUsd) : NumFloat::ceil($amountInUsd);
            $available      = ($available >= 0) ? NumFloat::floor($available) : NumFloat::ceil($available);
            $availableInUsd = ($availableInUsd >= 0) ? NumFloat::floor($availableInUsd) : NumFloat::ceil($availableInUsd);

            if ($currencyId == ModelCurrencies::USD_ID && ($amount != 0 || $available != 0)) {
                throw new Err("Position for USD is not allowed: ", func_get_args());
            }
            if (!NumFloat::isEqual($amount, $available) || !NumFloat::isEqual($amountInUsd, $availableInUsd)) {
                throw new Err("Bad position: Amount [$amount] is not equal to available [$available]: ", func_get_args());
            }
            if ($hold != 0 || $holdInUsd != 0) {
                throw new Err("Bad position: hold [$hold] or holdInUsd [$holdInUsd] are not allowed: ", func_get_args());
            }
        }
        else {
            $amount         = NumFloat::floor($amount);
            $amountInUsd    = NumFloat::floor($amountInUsd);
            $available      = NumFloat::floor($available);
            $availableInUsd = NumFloat::floor($availableInUsd);
            $hold           = NumFloat::floor($hold);
            $holdInUsd      = NumFloat::floor($holdInUsd);

            if ($hold && $hold > $amount) {
                throw new Err("Bad hold [$hold]: ", func_get_args());
            }
            if ($holdInUsd && $holdInUsd > $amountInUsd) {
                throw new Err("Bad holdInUsd [$holdInUsd]: ", func_get_args());
            }
        }

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
        if ($hold != 0 && $holdInUsd == 0) {
            throw new Err("Bad holdInUsd [$holdInUsd]: ", func_get_args());
        }

        $balance = $this->getEnabledBalanceByCurrencyIdAndType($currencyId, $type);
        if (!$balance) {
            throw new Err("Failed to get balance: ", func_get_args());
        }

        $this->beginTransaction();

        $affectedRows = $this->query(
            "UPDATE $this->_table
            SET %set% 
            WHERE %where% AND enabled = 1",
            ['%set%' => $this->filter([
                'amount'         => $amount,
                'amountInUsd'    => $amountInUsd,
                'available'      => $available,
                'availableInUsd' => $availableInUsd,
                'hold'           => $hold,
                'holdInUsd'      => $holdInUsd,
            ])],
            ['%where%' => [
                'currencyId' => $currencyId,
                'type' => $type
            ]]
        )->exec()->affectedRows(self::AFFECTED_ANY);

        if (
            $status
            && $statusCode != -1
            && ($balance['status'] != $status || $balance['statusCode'] != $statusCode)
        ) {
            $this->_updateStatus($balance['id'], $status, $statusCode, $statusMsg);

            if (in_array($status, [self::STATUS_SYNCED, self::STATUS_FAILED])) {
                $this->setSynced($balance['id']);
            }
        }

        if ($affectedRows) {
            ModelTotalBalancesLog::inst()->insert(
                $balance['id'], $currencyId, $amount, $amountInUsd, $available, $availableInUsd, $hold, $holdInUsd
            );
        }

        $this->commit();
    }
}