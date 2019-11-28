<?php
/**
 *
 */
class ClassAdminOrderDecompose
{
    // Correction of available in USD to smooth currency pair ratio difference between system internal and at exchanges
    const AVAILABLE_IN_USD_CORRECTION               = 0.95;

    const DECOMPOSED_CONTROL_AMOUNT_DEVIATION_MAX   = 0.05;
    const DECOMPOSED_AMOUNT_SHARES_SUM_MIN          = 0.95;

    private $_order = [];
    private $_orderDataSnapshot = [];
    private $_ordersDecomposedModel;
    private $_apiKeysSeen = [];
    private $_accountsTotal = 0;
    private $_accountsRejected = 0;

    function __construct(int $orderId)
    {
        ClassAdminOrderDecomposeErr::set($orderId);

        $order = ModelAdminsOrders::inst()->getOrderAndDataById($orderId);
        if (!$order) {
            throw new ClassAdminOrderDecomposeErr("Failed to get order");
        }

        $this->_checkOrder($order);
        $this->_order = $order;

        $this->_ordersDecomposedModel = ModelAbstractAdminsOrdersDecomposed::getModel($order['type']);
    }

    function run()
    {
        Verbose::echo1("Decomposing order [%s] type [%s]", $this->_order['id'], $this->_order['type']);
        Verbose::echo2("Order: ", $this->_order);

        switch ($this->_order['type']) {
            case ModelAdminsOrders::TYPE_NEW:
                $this->_decomposeOrderTypeNew();
                break;
            case ModelAdminsOrders::TYPE_REPLACE:
                $this->_decomposeOrderTypeReplace();
                break;
            case ModelAdminsOrders::TYPE_CANCEL:
                $this->_decomposeOrderTypeCancel();
                break;
            default:
                throw new ClassAdminOrderDecomposeErr("Bad condition");
        }
    }

    private function _decomposeOrderTypeNew()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        $this->_checkOrderTypeNew();

        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'],
            ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_DECOMPOSE
        );

        $this->_setOrderCurrency();
        $this->_setOrderExchanges();

        if ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE2) {
            $this->_setOrderDataSnapshot();
        }

        list($systemAccountsBalances, $usersAccountsBalances) = $this->_getAccountsBalances();

        $accounts = $this->_selectAccounts($systemAccountsBalances, $usersAccountsBalances);

        $dOrders = $this->_makeDecomposedOrdersTypeNew($accounts);

        Model::inst()->beginTransaction();

        $this->_ordersDecomposedModel->insert($this->_order['id'], $dOrders);

        if ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE2) {
            ModelAdminsOrders::inst()->updateAmount($this->_order['id'], $this->_order['type'], $this->_order['amount']);
            ModelAdminsOrders::inst()->updateRemain($this->_order['id'], $this->_order['type'], $this->_order['amount']);
        }
        ModelAdminsOrders::inst()->updateAvailableInUsdSum(
            $this->_order['id'], $this->_order['type'], $this->_order['availableInUsdSum']
        );
        ModelAdminsOrders::inst()->updateStats(
            $this->_order['id'], ['ordersDecomposedTotal' => count($dOrders), 'ordersDecomposedNew' => count($dOrders)]
        );
        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'],
            ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_WAIT_APPROVE
        );
        // NOTE: Skipped setting same status for decomposed orders as it is not necessary

        Model::inst()->commit();
    }

    private function _decomposeOrderTypeReplace()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        $this->_checkOrderTypeReplace();

        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'],
            ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_DECOMPOSE
        );

        $this->_setOrderCurrency();
        $this->_setOrderExchanges();

        ModelAdminsOrders::inst()->checkOrderToReplace($this->_order['replaceOrderId']);

        $orderToReplace = ModelAdminsOrders::inst()->getOrderById($this->_order['replaceOrderId']);
        if (!$orderToReplace) {
            throw new ClassAdminOrderDecomposeErr("Failed to get replace order [%s]", $this->_order['replaceOrderId']);
        }

        $mDecomposedOrdersToReplace = ModelAbstractAdminsOrdersDecomposed::getModel($orderToReplace['type']);

        $dOrdersToReplace = $mDecomposedOrdersToReplace->getOrdersByOrderId($this->_order['replaceOrderId'], Model::LIMIT_MAX);
        if (!$dOrdersToReplace) {
            throw new ClassAdminOrderDecomposeErr("Failed to get decomposed orders for replace order [%s]", $this->_order['replaceOrderId']);
        }

        if ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE2) {
            $this->_setOrderDataSnapshot();
        }

        list($systemAccountsBalances, $usersAccountsBalances) = $this->_getAccountsBalances();

        $accounts = $this->_selectAccounts($systemAccountsBalances, $usersAccountsBalances);

        $dOrders = $this->_makeDecomposedOrdersTypeReplace($accounts);

        $dOrdersCount = count($dOrders);
        $dOrdersToReplaceCount = count($dOrdersToReplace);
        if ($dOrdersCount != $dOrdersToReplaceCount) {
            throw new ClassAdminOrderDecomposeErr(
                "Mismatch counts for decomposed orders: dOrdersCount [$dOrdersCount] != dOrdersToReplaceCount [$dOrdersToReplaceCount]: Replace order [%s]",
                $this->_order['replaceOrderId']
            );
        }

        foreach ($dOrders as $i => &$dOrder) {
            $dOrder['replaceOrderDecomposedId'] = $dOrdersToReplace[$i]['id'];
        } unset($dOrder);

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->disableByGroupStrId($dOrdersToReplace[0]['requestGroupStrId']);

        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['replaceOrderId'],
            ModelAdminsOrders::STATUS_REJECTED, ModelAdminsOrders::STATUS_CODE_REJECTED_REPLACED
        );
        $mDecomposedOrdersToReplace->updateStatusesByOrderId(
            $this->_order['replaceOrderId'],
            $mDecomposedOrdersToReplace::STATUS_REJECTED, $mDecomposedOrdersToReplace::STATUS_CODE_REJECTED_REPLACED
        );

        $this->_ordersDecomposedModel->insert($this->_order['id'], $dOrders);

        if ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE2) {
            ModelAdminsOrders::inst()->updateAmount($this->_order['id'], $this->_order['type'], $this->_order['amount']);
            ModelAdminsOrders::inst()->updateRemain($this->_order['id'], $this->_order['type'], $this->_order['amount']);
        }
        ModelAdminsOrders::inst()->updateAvailableInUsdSum(
            $this->_order['id'], $this->_order['type'], $this->_order['availableInUsdSum']
        );
        ModelAdminsOrders::inst()->updateStats(
            $this->_order['id'], ['ordersDecomposedTotal' => count($dOrders), 'ordersDecomposedNew' => count($dOrders)]
        );
        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'],
            ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_WAIT_APPROVE
        );
        // NOTE: Skipped setting same status for decomposed orders as it is not necessary

        Model::inst()->commit();
    }

    private function _decomposeOrderTypeCancel()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        if (!$this->_order['cancelOrderId']) {
            throw new ClassAdminOrderDecomposeErr("Bad order: ", $this->_order);
        }

        ModelAdminsOrders::inst()->checkOrderToCancel($this->_order['cancelOrderId']);

        $orderToCancel = ModelAdminsOrders::inst()->getOrderById($this->_order['cancelOrderId']);
        if (!$orderToCancel) {
            throw new ClassAdminOrderDecomposeErr("Failed to get cancel order [%s]", $this->_order['cancelOrderId']);
        }

        $mDecomposedOrdersToCancel = ModelAbstractAdminsOrdersDecomposed::getModel($orderToCancel['type']);

        if (in_array($orderToCancel['statusCode'], [
            ModelAdminsOrders::STATUS_CODE_NEW,
            ModelAdminsOrders::STATUS_CODE_DOING_WAIT_APPROVE,
            ModelAdminsOrders::STATUS_CODE_DOING_APPROVED,
            // Other order statuses are not allowed here
        ])) {
            Verbose::echo2(
                "Order [%s] to cancel has status code [%s] and can be cancelled without decomposing",
                $this->_order['cancelOrderId'], $orderToCancel['statusCode']
            );

            Model::inst()->beginTransaction();

            ModelAdminsOrders::inst()->updateStatus(
                $this->_order['cancelOrderId'],
                ModelAdminsOrders::STATUS_REJECTED, ModelAdminsOrders::STATUS_CODE_REJECTED_CANCELLED
            );
            $mDecomposedOrdersToCancel->updateStatusesByOrderId(
                $this->_order['cancelOrderId'],
                $mDecomposedOrdersToCancel::STATUS_REJECTED, $mDecomposedOrdersToCancel::STATUS_CODE_REJECTED_CANCELLED
            );
            ModelAdminsOrders::inst()->updateStatus(
                $this->_order['id'],
                ModelAdminsOrders::STATUS_COMPLETED, ModelAdminsOrders::STATUS_CODE_COMPLETED
            );

            Model::inst()->commit();
        }
        elseif (in_array($orderToCancel['statusCode'], [
            ModelAdminsOrders::STATUS_CODE_DOING_CREATE_WAIT_REQ,
            ModelAdminsOrders::STATUS_CODE_DOING_CREATED,
            ModelAdminsOrders::STATUS_CODE_DOING_STATE_WAIT_REQ,
            // Other order statuses are not allowed here
        ])) {
            ModelAdminsOrders::inst()->updateStatus(
                $this->_order['id'],
                ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_DECOMPOSE
            );

            $dOrdersToCancel = $mDecomposedOrdersToCancel->getOrdersByOrderId($this->_order['cancelOrderId'], Model::LIMIT_MAX);
            if (!$dOrdersToCancel) {
                throw new ClassAdminOrderDecomposeErr("Failed to get decomposed orders for cancel order [%s]", $this->_order['cancelOrderId']);
            }

            $dOrders = $this->_makeDecomposedOrdersTypeCancel($dOrdersToCancel);

            Model::inst()->beginTransaction();

            ModelExchangesRequestsStack::inst()->disableByGroupStrId($dOrdersToCancel[0]['requestGroupStrId']);

            ModelAdminsOrders::inst()->updateStatus(
                $this->_order['cancelOrderId'],
                ModelAdminsOrders::STATUS_REJECTED, ModelAdminsOrders::STATUS_CODE_REJECTED_CANCELLED
            );
            $mDecomposedOrdersToCancel->updateStatusesByOrderId(
                $this->_order['cancelOrderId'],
                $mDecomposedOrdersToCancel::STATUS_REJECTED, $mDecomposedOrdersToCancel::STATUS_CODE_REJECTED_CANCELLED
            );

            $this->_ordersDecomposedModel->insert($this->_order['id'], $dOrders);

            ModelAdminsOrders::inst()->updateStats(
                $this->_order['id'], ['ordersDecomposedTotal' => count($dOrders), 'ordersDecomposedNew' => count($dOrders)]
            );

            ModelAdminsOrders::inst()->updateStatus(
                $this->_order['id'],
                ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_WAIT_APPROVE
            );
            // NOTE: Skipped setting same status for decomposed orders as it is not necessary

            Model::inst()->commit();
        }
        else {
            throw new ClassAdminOrderDecomposeErr("Bad status code [%s]", $orderToCancel['statusCode']);
        }
    }

    private function _setOrderDataSnapshot()
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        if (!$this->_order['dataSnapshotId']) {
            Verbose::echo2("Making new order data snapshot");

            $systemBalances = ModelSystemBalances::inst()->getBalancesInUsdForOrderDecompose(Model::LIMIT_MAX);
            $usersBalances = ModelUsersBalances::inst()->getBalancesInUsdForOrderDecompose(Model::LIMIT_MAX);

            $currPairsRatios = ModelCurrenciesPairsRatios::inst()->getRatios(Model::LIMIT_MAX, ['id', 'currPairId', 'exchangeId', 'ratio']);

            if (!$systemBalances && !$usersBalances && !$currPairsRatios) {
                throw new ClassAdminOrderDecomposeErr("Empty order data snapshot");
            }
            ModelAdminsOrdersDataSnapshots::inst()->insert($this->_order['id'], $systemBalances, $usersBalances, $currPairsRatios);
        }
        else {
            Verbose::echo2("Using order data snapshot [%s]", $this->_order['dataSnapshotId']);

            $this->_orderDataSnapshot = ModelAdminsOrdersDataSnapshots::inst()->getActiveSnapshotById($this->_order['dataSnapshotId']);
            if (!$this->_orderDataSnapshot) {
                throw new ClassAdminOrderDecomposeErr("Failed to get order data snapshot [%s]", $this->_order['dataSnapshotId']);
            }
        }
    }

    private function _getAccountsBalances(): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        if ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE1) {
            $systemAccountsBalances = ModelSystemBalances::inst()->getBalancesInUsdForOrderDecompose(Model::LIMIT_MAX);
            $usersAccountsBalances = ModelUsersBalances::inst()->getBalancesInUsdForOrderDecompose(Model::LIMIT_MAX);
        }
        elseif ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE2) {
            if (!$this->_orderDataSnapshot) {
                $systemAccountsBalances = ModelSystemBalances::inst()->getBalancesInUsdForOrderDecompose(Model::LIMIT_MAX);
                $usersAccountsBalances = ModelUsersBalances::inst()->getBalancesInUsdForOrderDecompose(Model::LIMIT_MAX);
            }
            else {
                $systemAccountsBalances = $this->_orderDataSnapshot['systemBalances'];
                $usersAccountsBalances = $this->_orderDataSnapshot['usersBalances'];
            }
        }
        else {
            throw new ClassAdminOrderDecomposeErr("Bad condition");
        }

        Verbose::echo2("Accounts balances [system]: ", count($systemAccountsBalances));
        Verbose::echo2("Accounts balances [user]: ", count($usersAccountsBalances));

        if (!$systemAccountsBalances && !$usersAccountsBalances) {
            throw new ClassAdminOrderDecomposeErr(
                "Failed to get accounts balances", (new ErrCode(ClassCronAdminsOrdersProcess::E_NO_ACCOUNTS_SELECTED))
            );
        }

        $this->_accountsTotal = count($systemAccountsBalances) + count($usersAccountsBalances);

        return [$systemAccountsBalances, $usersAccountsBalances];
    }

    private function _selectAccounts(array $systemAccountsBalances, array $usersAccountsBalances): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        $systemAccounts = $this->_selectSystemAccounts($systemAccountsBalances);
        Verbose::echo2("Selected accounts [system]: ", count($systemAccounts));

        $usersAccounts = $this->_selectUsersAccounts($usersAccountsBalances);
        Verbose::echo2("Selected accounts [user]: ", count($usersAccounts));

        $accounts = array_merge($systemAccounts, $usersAccounts);
        if (!$accounts) {
            throw new ClassAdminOrderDecomposeErr(
                "No accounts were selected", (new ErrCode(ClassCronAdminsOrdersProcess::E_NO_ACCOUNTS_SELECTED))
            );
        }

        return $accounts;
    }

    // NOTE: Onchange sync with _selectUsersAccounts()
    private function _selectSystemAccounts(array $systemAccountsBalances): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        if (!$systemAccountsBalances) {
            return [];
        }

        $accounts = [];
        foreach ($systemAccountsBalances as $balance) {
            Verbose::echo2(Verbose::EMPTY_LINE);
            Verbose::echo2("Doing api key [system:%s] balance", $balance['apiKeyId']);
            Verbose::echo2("Balance: ", $balance);

            $apiKey = ModelSystemApiKeys::inst()->getLiveKeyById($balance['apiKeyId']);
            if (!$apiKey) {
                Verbose::echo2("Skip: No live api key");
                $this->_accountsRejected++;
                continue;
            }
            Verbose::echo2("Api key: ", ClassAbstractApiKey::formatVerbose($apiKey));

            if (!empty($apiKey['userId'])) {
                throw new ClassAdminOrderDecomposeErr("Bad api key: ", ClassAbstractApiKey::formatVerbose($apiKey));
            }
            if (isset($this->_apiKeysSeen[$apiKey['hash']])) {
                Verbose::echo2("Skip: Already seen api key");
                $this->_accountsRejected++;
                continue;
            }
            $this->_apiKeysSeen[$apiKey['hash']] = true;

            $apiKeyExchangeMatch = false;
            foreach ($this->_order['__exchanges'] as $exchange) {
                if ($apiKey['exchangeId'] == $exchange['exchangeId']) {
                    $apiKeyExchangeMatch = true;
                    break;
                }
            }
            if (!$apiKeyExchangeMatch) {
                Verbose::echo2("Skip: Order exchange [%s] is not allowed for api key", $apiKey['exchangeId']);
                $this->_accountsRejected++;
                continue;
            }

            $apiKeyCurrPair = ModelSystemApiKeysCurrenciesPairs::inst()->getActivePairByApiKeyIdPairId($apiKey['id'], $this->_order['currPairId']);
            if (!$apiKeyCurrPair) {
                Verbose::echo2("Skip: Currency pair [%s] is not allowed for api key", $this->_order['currPairId']);
                $this->_accountsRejected++;
                continue;
            }
            Verbose::echo2("Api key curr pair: ", $apiKeyCurrPair);

            $exCurrPair = $this->_getExchangeCurrPair($apiKey['exchangeId'], $this->_order['currPairId']);
            if (!$exCurrPair) {
                Verbose::echo2("Skip: Currency pair [%s] is not allowed for exchange [%s]", $this->_order['currPairId'], $apiKey['exchangeId']);
                $this->_accountsRejected++;
                continue;
            }
            Verbose::echo2("Exchange curr pair: ", $exCurrPair);

            $apiKeySettings = ModelSystemApiKeysSettings::inst()->getSettingsRowByKeyId($apiKey['id']);
            if (!$apiKeySettings) {
                throw new ClassAdminOrderDecomposeErr("Failed to get api key [system:%s] settings", $apiKey['id']);
            }
            Verbose::echo2("Api key settings: ", $apiKeySettings);

            $exSettings = ModelExchangesSettings::inst()->getSettingsRowByExchangeId($apiKey['exchangeId']);
            if (!$exSettings) {
                throw new Err("Failed to get exchange [%s] settings", $apiKey['exchangeId']);
            }
            Verbose::echo2("Exchange settings: ", $exSettings);

            $availableInUsd = $this->_getAccountBalanceAvailableForOrderInUsd(
                $balance[ModelSystemBalances::TYPE_TRADING]['amountInUsdSum'], $balance[ModelSystemBalances::TYPE_POSITION]['amountInUsdSum'],
                $apiKeySettings['orderBalanceShareMax'], $exSettings['marginTradeAsset']
            );
            if ($availableInUsd <= 0) {
                Verbose::echo2("Skip: Balance available is zero or less: $availableInUsd");
                $this->_accountsRejected++;
                continue;
            }
            Verbose::echo2("Balance available in USD: $availableInUsd");

            $orderAmountMinInUsd = ClassCurrency::convertToUsd(
                $exCurrPair['orderAmountMin'], $this->_order['__currency1']['id'], $apiKey['exchangeId'],
                !empty($this->_orderDataSnapshot['currPairsRatios']) ? $this->_orderDataSnapshot['currPairsRatios'] : []
            );
            if ($availableInUsd < $orderAmountMinInUsd) {
                Verbose::echo2("Skip: Balance available in USD [$availableInUsd] < orderAmountMinInUsd [$orderAmountMinInUsd]");
                $this->_accountsRejected++;
                continue;
            }

            $account = [
                'entity'            => ModelSystemApiKeys::ENTITY_SYSTEM,
                'systemApiKeyId'    => $apiKey['id'],
                'userApiKeyId'      => 0,
                'exchangeId'        => $apiKey['exchangeId'],
                'availableInUsd'    => $availableInUsd,
            ];
            Verbose::echo2("Account selected [system]: ", $account);

            $accounts[] = $account;
        }

        return $accounts;
    }

    // NOTE: Onchange sync with _selectSystemAccounts()
    private function _selectUsersAccounts(array $usersAccountsBalances): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        if (!$usersAccountsBalances) {
            return [];
        }

        $accounts = [];
        foreach ($usersAccountsBalances as $balance) {
            Verbose::echo2(Verbose::EMPTY_LINE);
            Verbose::echo2("Doing api key [user:%s] balance", $balance['apiKeyId']);
            Verbose::echo2("Balance: ", $balance);

            $apiKey = ModelUsersApiKeys::inst()->getLiveKeyById($balance['apiKeyId']);
            if (!$apiKey) {
                Verbose::echo2("Skip: No live api key");
                $this->_accountsRejected++;
                continue;
            }
            Verbose::echo2("Api key: ", ClassAbstractApiKey::formatVerbose($apiKey));

            if (empty($apiKey['userId'])) {
                throw new ClassAdminOrderDecomposeErr("Bad api key: ", ClassAbstractApiKey::formatVerbose($apiKey));
            }
            if (isset($this->_apiKeysSeen[$apiKey['hash']])) {
                Verbose::echo2("Skip: Already seen api key");
                $this->_accountsRejected++;
                continue;
            }
            $this->_apiKeysSeen[$apiKey['hash']] = true;

            $user = ModelUsers::inst()->getActiveUserById($apiKey['userId']);
            if (!$user) {
                Verbose::echo2("Skip: Inactive user [%s]", $apiKey['userId']);
                $this->_accountsRejected++;
                continue;
            }
            Verbose::echo2("User: ", $user);

            $apiKeyExchangeMatch = false;
            foreach ($this->_order['__exchanges'] as $exchange) {
                if ($apiKey['exchangeId'] == $exchange['exchangeId']) {
                    $apiKeyExchangeMatch = true;
                    break;
                }
            }
            if (!$apiKeyExchangeMatch) {
                Verbose::echo2("Skip: Order exchange [%s] is not allowed for api key", $apiKey['exchangeId']);
                $this->_accountsRejected++;
                continue;
            }

            $apiKeyCurrPair = ModelUsersApiKeysCurrenciesPairs::inst()->getActivePairByApiKeyIdPairId($apiKey['id'], $this->_order['currPairId']);
            if (!$apiKeyCurrPair) {
                Verbose::echo2("Skip: Currency pair [%s] is not allowed for api key", $this->_order['currPairId']);
                $this->_accountsRejected++;
                continue;
            }
            Verbose::echo2("Api key curr pair: ", $apiKeyCurrPair);

            $exCurrPair = $this->_getExchangeCurrPair($apiKey['exchangeId'], $this->_order['currPairId']);
            if (!$exCurrPair) {
                Verbose::echo2("Skip: Currency pair [%s] is not allowed for exchange [%s]", $this->_order['currPairId'], $apiKey['exchangeId']);
                $this->_accountsRejected++;
                continue;
            }
            Verbose::echo2("Exchange curr pair: ", $exCurrPair);

            $apiKeySettings = ModelUsersApiKeysSettings::inst()->getSettingsRowByKeyId($apiKey['id']);
            if (!$apiKeySettings) {
                throw new ClassAdminOrderDecomposeErr("Failed to get api key [user:%s] settings", $apiKey['id']);
            }
            Verbose::echo2("Api key settings: ", $apiKeySettings);

            $exSettings = ModelExchangesSettings::inst()->getSettingsRowByExchangeId($apiKey['exchangeId']);
            if (!$exSettings) {
                throw new Err("Failed to get exchange [%s] settings", $apiKey['exchangeId']);
            }
            Verbose::echo2("Exchange settings: ", $exSettings);

            $availableInUsd = $this->_getAccountBalanceAvailableForOrderInUsd(
                $balance[ModelUsersBalances::TYPE_TRADING]['amountInUsdSum'], $balance[ModelUsersBalances::TYPE_POSITION]['amountInUsdSum'],
                $apiKeySettings['orderBalanceShareMax'], $exSettings['marginTradeAsset']
            );
            if ($availableInUsd <= 0) {
                Verbose::echo2("Skip: Balance available is zero or less: $availableInUsd");
                $this->_accountsRejected++;
                continue;
            }
            Verbose::echo2("Balance available in USD: $availableInUsd");

            $orderAmountMinInUsd = ClassCurrency::convertToUsd(
                $exCurrPair['orderAmountMin'], $this->_order['__currency1']['id'], $apiKey['exchangeId'],
                !empty($this->_orderDataSnapshot['currPairsRatios']) ? $this->_orderDataSnapshot['currPairsRatios'] : []
            );
            if ($availableInUsd < $orderAmountMinInUsd) {
                Verbose::echo2("Skip: Balance available in USD [$availableInUsd] < orderAmountMinInUsd [$orderAmountMinInUsd]");
                $this->_accountsRejected++;
                continue;
            }

            $account = [
                'entity'            => ModelUsersApiKeys::ENTITY_USER,
                'systemApiKeyId'    => 0,
                'userApiKeyId'      => $apiKey['id'],
                'exchangeId'        => $apiKey['exchangeId'],
                'availableInUsd'    => $availableInUsd,
            ];
            Verbose::echo2("Account selected [user]: ", $account);

            $accounts[] = $account;
        }

        return $accounts;
    }

    private function _makeDecomposedOrdersTypeNew(array $accounts): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        if ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE1) {
            $dOrders = $this->_makeDecomposedOrdersTypeNewComplexityType1($accounts);
        }
        elseif ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE2) {
            $dOrders = $this->_makeDecomposedOrdersTypeNewComplexityType2($accounts);
        }
        else {
            throw new ClassAdminOrderDecomposeErr("Bad condition");
        }

        if (!$dOrders) {
            throw new ClassAdminOrderDecomposeErr("No decomposed orders");
        }

        Verbose::echo2("Accounts total [$this->_accountsTotal] / rejected [$this->_accountsRejected]");
        Verbose::echo2("Decomposed orders: ", count($dOrders));

        return $dOrders;
    }

    private function _makeDecomposedOrdersTypeNewComplexityType1(array $accounts): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        $orderAmount = $this->_order['amount'];
        $accounts = $this->_filterAccountsByAmountShare($accounts, $this->_getAvailableInUsdSum($accounts), $orderAmount);

        $availableInUsdSum = $this->_getAvailableInUsdSum($accounts);
        Verbose::echo2("Available in USD sum: $availableInUsdSum");
        $this->_order['availableInUsdSum'] = $availableInUsdSum;

        $controlAmount = $orderAmount;
        $dOrdersAmountSum = 0;
        $dOrdersSharesSum = 0;
        $dOrdersAmountExceed = 0;

        $dOrders = [];
        foreach ($accounts as $account) {
            Verbose::echo2("Doing account: ", $account);

            $amountShare = NumFloat::floor($account['availableInUsd'] / $availableInUsdSum);
            if ($amountShare <= 0 || $amountShare > 1) {
                throw new ClassAdminOrderDecomposeErr(
                    "Bad decomposed order amountShare [$amountShare]: availableInUsd [%s] / availableInUsdSum [$availableInUsdSum]", $account['availableInUsd']
                );
            }
            $dOrdersSharesSum += $amountShare;

            $amount = NumFloat::floor($orderAmount * $amountShare);

            $exCurrPair = $this->_getExchangeCurrPair($account['exchangeId'], $this->_order['currPairId']);
            if (!$exCurrPair) {
                throw new ClassAdminOrderDecomposeErr("Failed to get exchange [%s] curr pair [%s]", $account['exchangeId'], $this->_order['currPairId']);
            }
            if ($amount < $exCurrPair['orderAmountMin'] || $amount > $exCurrPair['orderAmountMax']) {
                Verbose::echo2(
                    "Skip: Decomposed order amount [$amount] exceeds allowed order amount range [%s - %s]",
                    $exCurrPair['orderAmountMin'], $exCurrPair['orderAmountMax']
                );
                $controlAmount -= $amount;
                $dOrdersAmountExceed++;
                $this->_accountsRejected++;
                continue;
            }

            $dOrdersAmountSum += $amount;

            $dOrder = [
                'systemApiKeyId'    => $account['systemApiKeyId'],
                'userApiKeyId'      => $account['userApiKeyId'],
                'exchangeId'        => $account['exchangeId'],
                'currPairId'        => $this->_order['currPairId'],
                'share'             => $amountShare,
                'amount'            => $amount,
                'price'             => $this->_order['price'],
                'side'              => $this->_order['side'],
                'exec'              => $this->_order['exec'],
            ];
            Verbose::echo2("Decomposed order: ", $dOrder);

            $dOrders[] = $dOrder;
        }

        if (!$dOrders) {
            if ($dOrdersAmountExceed == count($accounts)) {
                throw new ClassAdminOrderDecomposeErr(
                    "All decomposed orders have bad amount (too low or too high)", (new ErrCode(ClassCronAdminsOrdersProcess::E_NO_DECOMPOSED_ORDERS))
                );
            }
            throw new ClassAdminOrderDecomposeErr("No decomposed orders", (new ErrCode(ClassCronAdminsOrdersProcess::E_NO_DECOMPOSED_ORDERS)));
        }

        if ($dOrdersAmountSum <= 0) {
            throw new ClassAdminOrderDecomposeErr("Bad dOrdersAmountSum [$dOrdersAmountSum]");
        }
        if ($dOrdersAmountSum > $orderAmount) {
            throw new ClassAdminOrderDecomposeErr("dOrdersAmountSum [$dOrdersAmountSum] > order amount [$orderAmount]");
        }

        $orderAmountDeviatedMax = $orderAmount * (1 - self::DECOMPOSED_CONTROL_AMOUNT_DEVIATION_MAX);
        if ($controlAmount < $orderAmountDeviatedMax) {
            throw new ClassAdminOrderDecomposeErr("Decomposed controlAmount [$controlAmount] < orderAmountDeviatedMax [$orderAmountDeviatedMax]");
        }

        $smoothRoundDeviation = 0.9999999;
        if ($dOrdersAmountSum > $controlAmount || $dOrdersAmountSum < $controlAmount * $smoothRoundDeviation) {
            throw new ClassAdminOrderDecomposeErr("Order dOrdersAmountSum [$dOrdersAmountSum] != controlAmount [~$controlAmount]");
        }

        if ($dOrdersSharesSum < self::DECOMPOSED_AMOUNT_SHARES_SUM_MIN || $dOrdersSharesSum > 1) {
            throw new ClassAdminOrderDecomposeErr("Bad dOrdersSharesSum [$dOrdersSharesSum]");
        }
        Verbose::echo2("Decomposed orders shares sum: $dOrdersSharesSum");

        return $dOrders;
    }

    private function _makeDecomposedOrdersTypeNewComplexityType2(array $accounts): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        $accounts = $this->_filterAccountsByAmountShare(
            $accounts, $this->_getAvailableInUsdSum($accounts), $this->_getOrderAmountComplexityType2($accounts)
        );

        $availableInUsdSum = $this->_getAvailableInUsdSum($accounts);
        Verbose::echo2("Available in USD sum: $availableInUsdSum");
        $this->_order['availableInUsdSum'] = $availableInUsdSum;

        $orderAmount = $this->_getOrderAmountComplexityType2($accounts);
        Verbose::echo2("Order amount: ", $orderAmount);
        $this->_order['amount'] = $orderAmount;

        $dOrdersAmountSum = 0;
        $dOrdersAmountInUsdSum = 0;
        $dOrdersSharesSum = 0;
        $dOrdersAmountExceed = 0;

        $dOrders = [];
        foreach ($accounts as $account) {
            Verbose::echo2("Doing account: ", $account);

            $amountShare = NumFloat::floor($account['availableInUsd'] / $availableInUsdSum);
            $dOrdersSharesSum += $amountShare;

            list($amount, $amountInUsd) = $this->_getDecomposedOrderAmountComplexityType2($account['availableInUsd'], $account['exchangeId']);

            $exCurrPair = $this->_getExchangeCurrPair($account['exchangeId'], $this->_order['currPairId']);
            if (!$exCurrPair) {
                throw new ClassAdminOrderDecomposeErr("Failed to get exchange [%s] curr pair [%s]", $account['exchangeId'], $this->_order['currPairId']);
            }
            if ($amount < $exCurrPair['orderAmountMin'] || $amount > $exCurrPair['orderAmountMax']) {
                Verbose::echo2(
                    "Skip: Decomposed order amount [$amount] exceeds allowed order amount range [%s - %s]",
                    $exCurrPair['orderAmountMin'], $exCurrPair['orderAmountMax']
                );
                $dOrdersAmountExceed++;
                $this->_accountsRejected++;
                continue;
            }

            $dOrdersAmountSum += $amount;
            $dOrdersAmountInUsdSum += $amountInUsd;

            $dOrder = [
                'systemApiKeyId'    => $account['systemApiKeyId'],
                'userApiKeyId'      => $account['userApiKeyId'],
                'exchangeId'        => $account['exchangeId'],
                'currPairId'        => $this->_order['currPairId'],
                'share'             => $amountShare,
                'amount'            => $amount,
                'price'             => $this->_order['price'],
                'side'              => $this->_order['side'],
                'exec'              => $this->_order['exec'],
            ];
            Verbose::echo2("Decomposed order: ", $dOrder);

            $dOrders[] = $dOrder;
        }

        if (!$dOrders) {
            if ($dOrdersAmountExceed == count($accounts)) {
                throw new ClassAdminOrderDecomposeErr(
                    "All decomposed orders have bad amount (too low or too high)", (new ErrCode(ClassCronAdminsOrdersProcess::E_NO_DECOMPOSED_ORDERS))
                );
            }
            throw new ClassAdminOrderDecomposeErr("No decomposed orders", (new ErrCode(ClassCronAdminsOrdersProcess::E_NO_DECOMPOSED_ORDERS)));
        }

        if ($dOrdersAmountSum <= 0) {
            throw new ClassAdminOrderDecomposeErr("Bad dOrdersAmountSum [$dOrdersAmountSum]");
        }

        if ($dOrdersAmountInUsdSum <= 0) {
            throw new ClassAdminOrderDecomposeErr("Bad dOrdersAmountInUsdSum [$dOrdersAmountInUsdSum]");
        }
        if ($dOrdersAmountInUsdSum > $availableInUsdSum) {
            throw new ClassAdminOrderDecomposeErr("dOrdersAmountInUsdSum [$dOrdersAmountInUsdSum] > availableInUsdSum [$availableInUsdSum]");
        }

        if ($dOrdersSharesSum < self::DECOMPOSED_AMOUNT_SHARES_SUM_MIN || $dOrdersSharesSum > 1) {
            throw new ClassAdminOrderDecomposeErr("Bad dOrdersSharesSum [$dOrdersSharesSum]");
        }
        Verbose::echo2("Decomposed orders shares sum: $dOrdersSharesSum");

        return $dOrders;
    }

    private function _makeDecomposedOrdersTypeReplace(array $accounts): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);
        return $this->_makeDecomposedOrdersTypeNew($accounts);
    }

    private function _makeDecomposedOrdersTypeCancel(array $dOrdersToCancel): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        $dOrders = [];
        foreach ($dOrdersToCancel as $dOrder) {
            Verbose::echo2("Doing decomposed order to cancel from: ", $dOrder);

            ModelAbstractApiKeys::checkIds($dOrder['systemApiKeyId'], $dOrder['userApiKeyId']);

            $dOrder = [
                'cancelOrderDecomposedId'   => $dOrder['id'],
                'systemApiKeyId'            => $dOrder['systemApiKeyId'],
                'userApiKeyId'              => $dOrder['userApiKeyId'],
                'exchangeId'                => $dOrder['exchangeId'],
            ];
            Verbose::echo2("Decomposed cancel order result: ", $dOrder);

            $dOrders[] = $dOrder;
        }

        if (!$dOrders) {
            throw new ClassAdminOrderDecomposeErr("No decomposed orders", (new ErrCode(ClassCronAdminsOrdersProcess::E_NO_DECOMPOSED_ORDERS)));
        }

        Verbose::echo2("Decomposed orders: ", count($dOrders));

        return $dOrders;
    }

    private function _checkOrder(array $order)
    {
        if (
            !$order['id']
            || !$order['groupId']
            || !in_array($order['type'], ModelAdminsOrders::getTypes())
            || $order['status'] != ModelAdminsOrders::STATUS_NEW
            || $order['statusCode'] != ModelAdminsOrders::STATUS_CODE_NEW
            || !$order['adminId']
            || $order['approvedAdminId'] != 0
            || !$order['enabled']
        ) {
            throw new ClassAdminOrderDecomposeErr("Bad order: ", $order);
        }
    }

    private function _checkOrderTypeNew()
    {
        if ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE1) {
            if (
                $this->_order['dataSnapshotId']
                || !$this->_order['currPairId']
                || $this->_order['amountPrice'] != 0
                || $this->_order['amountMultiplier'] != 0
                || $this->_order['amount'] <= 0
                || $this->_order['remain'] != $this->_order['amount']
                || $this->_order['price'] <= 0
                || !in_array($this->_order['side'], ModelAdminsOrders::getSides())
                || !in_array($this->_order['exec'], ModelAdminsOrders::getExecs())
            ) {
                throw new ClassAdminOrderDecomposeErr("Bad order: ", $this->_order);
            }
        }
        elseif ($this->_order['complexity'] == ModelAdminsOrders::COMPLEXITY_TYPE2) {
            if (
                !$this->_order['currPairId']
                || $this->_order['amountPrice'] <= 0
                || $this->_order['amountMultiplier'] <= 0
                || $this->_order['amount'] != 0
                || $this->_order['remain'] != 0
                || $this->_order['price'] <= 0
                || !in_array($this->_order['side'], ModelAdminsOrders::getSides())
                || !in_array($this->_order['exec'], ModelAdminsOrders::getExecs())
            ) {
                throw new ClassAdminOrderDecomposeErr("Bad order: ", $this->_order);
            }
        }
        else {
            throw new ClassAdminOrderDecomposeErr("Bad condition");
        }
    }

    private function _checkOrderTypeReplace()
    {
        $this->_checkOrderTypeNew();
        if (!$this->_order['replaceOrderId']) {
            throw new ClassAdminOrderDecomposeErr("Bad order: ", $this->_order);
        }
    }

    private function _setOrderCurrency()
    {
        $currPair = ModelCurrenciesPairs::inst()->getActivePairById($this->_order['currPairId']);
        if (!$currPair) {
            throw new ClassAdminOrderDecomposeErr("Failed to get active currency pair [%s]", $this->_order['currPairId']);
        }
        Verbose::echo2("Curr pair: ", $currPair);

        $currency1 = ModelCurrencies::inst()->getActiveCurrencyById($currPair['currency1Id']);
        if (!$currency1) {
            throw new ClassAdminOrderDecomposeErr("Failed to get active currency [%s]", $currPair['currency1Id']);
        }
        Verbose::echo2("Currency1: ", $currency1);

        $currency2 = ModelCurrencies::inst()->getActiveCurrencyById($currPair['currency2Id']);
        if (!$currency2) {
            throw new ClassAdminOrderDecomposeErr("Failed to get active currency [%s]", $currPair['currency2Id']);
        }
        Verbose::echo2("Currency2: ", $currency2);

        $currPairSettings = ModelCurrenciesPairsSettings::inst()->getSettingsRowByPairId($this->_order['currPairId']);
        if (!$currPairSettings) {
            throw new ClassAdminOrderDecomposeErr("Failed to get curr pair [%s] settings", $this->_order['currPairId']);
        }
        if ($currPairSettings['ordersCount'] == 0) {
            throw new ClassAdminOrderDecomposeErr("Bad curr pair [%s] settings", $currPairSettings);
        }
        Verbose::echo2("Curr pair settings: ", $currPairSettings);

        $this->_order['__currPair']          = $currPair;
        $this->_order['__currPairSettings']  = $currPairSettings;
        $this->_order['__currency1']         = $currency1;
        $this->_order['__currency2']         = $currency2;
    }

    private function _setOrderExchanges()
    {
        $exchanges = ModelAdminsOrders::inst()->getOrderActiveExchangesById($this->_order['id']);
        if (!$exchanges) {
            throw new ClassAdminOrderDecomposeErr("Failed to get order active exchanges");
        }
        Verbose::echo2("Order exchanges: ", $exchanges);

        $this->_order['__exchanges'] = $exchanges;
    }

    private function _countTradeableCurrPairs(int $exchangeId): int
    {
        return Cache::make(
            [__CLASS__, __FUNCTION__, $exchangeId],
            function() use($exchangeId): int {
                $count = 0;
                $exPairs = ModelExchangesCurrenciesPairs::inst()->getActivePairsByExchangeId($exchangeId, Model::LIMIT_MAX);
                if ($exPairs) {
                    foreach ($exPairs as $exPair) {
                        $pair = ModelCurrenciesPairs::inst()->getActivePairById($exPair['currPairId']);
                        if ($pair) {
                            if (
                                ModelCurrencies::inst()->getActiveCurrencyById($pair['currency1Id'], ['id'])
                                && ModelCurrencies::inst()->getActiveCurrencyById($pair['currency2Id'], ['id'])
                            ) {
                                $count++;
                            }
                        }
                    }
                }
                if (!$count) {
                    throw new ClassAdminOrderDecomposeErr("No tradeable curr pairs for exchange [$exchangeId]");
                }
                return $count;
            },
            Cache::EXPIRE_SEC * 5
        );
    }

    // Filter accounts by amount * share < > order min/max amount
    private function _filterAccountsByAmountShare(array $accounts, float $availableInUsdSum, float $orderAmount): array
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        $res = [];
        foreach ($accounts as $account) {
            Verbose::echo2("Doing account: ", $account);

            $amountShare = NumFloat::floor($account['availableInUsd'] / $availableInUsdSum);
            if ($amountShare <= 0 || $amountShare > 1) {
                throw new ClassAdminOrderDecomposeErr(
                    "Bad decomposed order amountShare [$amountShare]: availableInUsd [%s] / availableInUsdSum [$availableInUsdSum]", $account['availableInUsd']
                );
            }

            $amount = NumFloat::floor($orderAmount * $amountShare);

            $exCurrPair = $this->_getExchangeCurrPair($account['exchangeId'], $this->_order['currPairId']);
            if (!$exCurrPair) {
                throw new ClassAdminOrderDecomposeErr("Failed to get exchange [%s] curr pair [%s]", $account['exchangeId'], $this->_order['currPairId']);
            }
            if ($amount < $exCurrPair['orderAmountMin'] || $amount > $exCurrPair['orderAmountMax']) {
                Verbose::echo2(
                    "Skip: Decomposed order amount [$amount] exceeds allowed order amount range [%s - %s]",
                    $exCurrPair['orderAmountMin'], $exCurrPair['orderAmountMax']
                );
                $this->_accountsRejected++;
                continue;
            }

            $res[] = $account;
        }
        if (!$res) {
            throw new ClassAdminOrderDecomposeErr("No accounts after filtering", (new ErrCode(ClassCronAdminsOrdersProcess::E_NO_ACCOUNTS_SELECTED)));
        }

        return $res;
    }

    private function _getAccountBalanceAvailableForOrderInUsd(
        float $tradingAmountInUsd, float $positionAmountInUsd, float $orderBalanceShareMax, float $marginTradeAsset
    ): float
    {
        if ($orderBalanceShareMax <= 0 || $marginTradeAsset <= 0) {
            return 0;
        }
        $available = $tradingAmountInUsd;
        $available = $available * $orderBalanceShareMax * $marginTradeAsset;
        // NOTE: Disabled: Exact available funds checks is being done at exchange level
        //$available = $available - $positionAmountInUsd;
        $available = $available * self::AVAILABLE_IN_USD_CORRECTION;
        $available = NumFloat::floor($available);
        return $available;
    }

    private function _getAvailableInUsdSum(array $accounts): float
    {
        $availableInUsdSum = 0;
        foreach ($accounts as $account) {
            $availableInUsdSum += $account['availableInUsd'];
        }
        if ($availableInUsdSum <= 0) {
            throw new ClassAdminOrderDecomposeErr("Bad availableInUsdSum [$availableInUsdSum]");
        }
        return $availableInUsdSum;
    }

    private function _getExchangeCurrPair(int $exchangeId, int $currPairId): array
    {
        return Cache::make(
            [__CLASS__, __FUNCTION__, $exchangeId, $currPairId],
            function() use($exchangeId, $currPairId): array {
                return ModelExchangesCurrenciesPairs::inst()->getActivePairByExchangeIdPairId($exchangeId, $currPairId);
            },
            Cache::EXPIRE_SEC * 5
        );
    }

    private function _getOrderAmountComplexityType2(array $accounts): float
    {
        $totalAmount = 0;
        foreach ($accounts as $account) {
            list($amount, $_) = $this->_getDecomposedOrderAmountComplexityType2($account['availableInUsd'], $account['exchangeId']);
            $totalAmount += $amount;
        }
        if ($totalAmount <= 0) {
            throw new ClassAdminOrderDecomposeErr("Bad order total amount [$totalAmount]");
        }
        return $totalAmount;
    }

    private function _getDecomposedOrderAmountComplexityType2(float $accountAvailableInUsd, int $accountExchangeId): array
    {
        $tradeableCurrPairs = $this->_countTradeableCurrPairs($accountExchangeId);

        if (!$tradeableCurrPairs || !$this->_order['__currPairSettings']['ordersCount'] || !$this->_order['amountPrice']) {
            throw new ClassAdminOrderDecomposeErr("Zero values for division");
        }

        $amount = $accountAvailableInUsd
                    / $tradeableCurrPairs
                    / $this->_order['__currPairSettings']['ordersCount']
                    / $this->_order['amountPrice'];

        $amount = NumFloat::floor($amount, 1);
        $amount = $amount * $this->_order['amountMultiplier'];

        $amountInUsd = ClassCurrency::convertToUsd(
            $amount, $this->_order['__currency1']['id'], $accountExchangeId,
            !empty($this->_orderDataSnapshot['currPairsRatios']) ? $this->_orderDataSnapshot['currPairsRatios'] : []
        );

        Verbose::echo2(
            "amount [$amount] = floor( accountAvailableInUsd [$accountAvailableInUsd]" .
            " / tradeableCurrPairs [$tradeableCurrPairs]" .
            " / ~currPairOrdersCount [%s] " .
            " / amountPrice [%s], 1 )".
            " * amountMultiplier [%s]",
            $this->_order['__currPairSettings']['ordersCount'], $this->_order['amountPrice'], $this->_order['amountMultiplier']
        );

        return [$amount, $amountInUsd];
    }
}