<?php
/**
 * Check decomposed orders at exchanges
 */
class ClassAdminOrderCheckDecomposed
{
    private $_order = [];
    private $_ordersDecomposed = [];
    private $_ordersDecomposedModel;

    function __construct(int $orderId)
    {
        ClassAdminOrderCheckDecomposedErr::set($orderId);

        $order = ModelAdminsOrders::inst()->getOrderAndDataById($orderId);
        if (!$order) {
            throw new ClassAdminOrderCheckDecomposedErr("Failed to get order");
        }
        if (
            !in_array($order['type'], ModelAdminsOrders::getTypes())
            || $order['status'] != ModelAdminsOrders::STATUS_DOING
            || !in_array($order['statusCode'], [
                ModelAdminsOrders::STATUS_CODE_DOING_CREATE_WAIT_REQ,
                ModelAdminsOrders::STATUS_CODE_DOING_CREATED,
                ModelAdminsOrders::STATUS_CODE_DOING_CANCELLED,
                ModelAdminsOrders::STATUS_CODE_DOING_STATE_WAIT_REQ,
            ])
            || !$order['approvedAdminId']
            || !$order['enabled']
        ) {
            throw new ClassAdminOrderCheckDecomposedErr("Bad order: ", $order);
        }
        $this->_order = $order;

        $this->_ordersDecomposedModel = ModelAbstractAdminsOrdersDecomposed::getModel($order['type']);

        $dOrders = $this->_ordersDecomposedModel->getActiveOrdersByOrderIdAndStatusCodes(
            $orderId, [
                $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ,
                $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATED,
                $this->_ordersDecomposedModel::STATUS_CODE_DOING_CANCELLED,
                $this->_ordersDecomposedModel::STATUS_CODE_DOING_STATE_WAIT_REQ,
            ],
            Model::LIMIT_MAX
        );
        if (!$dOrders) {
            throw new ClassAdminOrderCheckDecomposedErr("Failed to get active decomposed orders");
        }
        $this->_ordersDecomposed = $dOrders;
    }

    function run()
    {
        Verbose::echo1("Checking order [%s] type [%s] decomposed orders at exchanges", $this->_order['id'], $this->_order['type']);
        Verbose::echo2("Order: ", $this->_order);
        Verbose::echo2("Total decomposed orders: ", count($this->_ordersDecomposed));

        switch ($this->_order['type']) {
            case ModelAdminsOrders::TYPE_NEW:
                $this->_checkDecomposedOrdersTypeNew();
                break;
            case ModelAdminsOrders::TYPE_REPLACE:
                $this->_checkDecomposedOrdersTypeReplace();
                break;
            case ModelAdminsOrders::TYPE_CANCEL:
                $this->_checkDecomposedOrdersTypeCancel();
                break;
            default:
                throw new ClassAdminOrderCheckDecomposedErr("Bad condition");
        }
    }

    protected function _checkDecomposedOrdersTypeNew()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        // NOTE: Not changing order status before decomposed orders processing

        foreach ($this->_ordersDecomposed as $dOrder) {
            Verbose::echo2("Checking order [%s] decomposed order [%s]", $this->_order['id'], $dOrder['id']);
            Verbose::echo2("Decomposed order: ", $dOrder);

            $this->_validateDecomposedOrderTypeNew($dOrder);

            switch ($dOrder['statusCode']) {
                case $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ:
                    $this->_checkDecomposedOrderTypeNewCreateWaitReq($dOrder);
                    break;
                case $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATED:
                    $this->_checkDecomposedOrderTypeNewCreated($dOrder);
                    break;
                case $this->_ordersDecomposedModel::STATUS_CODE_DOING_STATE_WAIT_REQ:
                    $this->_checkDecomposedOrderTypeNewStatusWaitReq($dOrder);
                    break;
                default:
                    throw new ClassAdminOrderCheckDecomposedErr("Bad condition");
            }
        }

        $this->_updateOrderByDecomposedOrders();
    }

    protected function _checkDecomposedOrdersTypeReplace()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        // NOTE: Not changing order status before decomposed orders processing

        foreach ($this->_ordersDecomposed as $dOrder) {
            Verbose::echo2("Checking order [%s] decomposed order [%s]", $this->_order['id'], $dOrder['id']);
            Verbose::echo2("Decomposed order: ", $dOrder);

            $this->_validateDecomposedOrderTypeReplace($dOrder);

            switch ($dOrder['statusCode']) {
                case $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ:
                    $this->_checkDecomposedOrderTypeReplaceCreateWaitReq($dOrder);
                    break;
                case $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATED:
                    $this->_checkDecomposedOrderTypeReplaceCreated($dOrder);
                    break;
                case $this->_ordersDecomposedModel::STATUS_CODE_DOING_STATE_WAIT_REQ:
                    $this->_checkDecomposedOrderTypeReplaceStatusWaitReq($dOrder);
                    break;
                default:
                    throw new ClassAdminOrderCheckDecomposedErr("Bad condition");
            }
        }

        $this->_updateOrderByDecomposedOrders();
    }

    protected function _checkDecomposedOrdersTypeCancel()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        // NOTE: Not changing order status before decomposed orders processing

        foreach ($this->_ordersDecomposed as $dOrder) {
            Verbose::echo2("Checking order [%s] decomposed order [%s]", $this->_order['id'], $dOrder['id']);
            Verbose::echo2("Decomposed order: ", $dOrder);

            $this->_validateDecomposedOrderTypeCancel($dOrder);

            switch ($dOrder['statusCode']) {
                case $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ:
                    $this->_checkDecomposedOrderTypeCancel($dOrder);
                    break;
                default:
                    throw new ClassAdminOrderCheckDecomposedErr("Bad condition");
            }
        }

        $this->_updateOrderByDecomposedOrders();
    }

    private function _checkDecomposedOrderTypeNewCreateWaitReq(array $dOrder)
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        $request = ModelExchangesRequestsStack::inst()->getAndValidateUnprocessedRequestByStrId($dOrder['requestStrId']);
        Verbose::echo2("Request stack id: ", $request['id']);
        Verbose::echo2("Request to exchange: ", ModelExchangesRequestsStack::formatVerbose($request));

        if ($request['__inProgress']) {
            Verbose::echo2("Request stack [%s] is in progress with status [%s]", $request['id'], $request['status']);
            return;
        }

        $error = '';
        $exchangeOrderId = ClassAbstractExchangeApi::getApi($dOrder['exchangeId'])->parseResponseCreatedOrderTypeNew(
            $request['status'], $request['responseBody'], $error, $dOrder
        );
        if (!$exchangeOrderId) {
            $this->_setDecomposedOrderAsFailed($dOrder['id'], $request['id'], $error);
            return;
        }

        Verbose::echo2("Order id at exchange: $exchangeOrderId");

        $this->_ordersDecomposedModel->setExchangeOrderId($dOrder['id'], $exchangeOrderId);

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($request['id']);

        $this->_ordersDecomposedModel->updateStatus(
            $dOrder['id'], $this->_ordersDecomposedModel::STATUS_DOING, $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATED
        );

        Model::inst()->commit();
    }

    private function _checkDecomposedOrderTypeReplaceCreateWaitReq(array $dOrder)
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        $request = ModelExchangesRequestsStack::inst()->getAndValidateUnprocessedRequestByStrId($dOrder['requestStrId']);
        Verbose::echo2("Request stack id: ", $request['id']);
        Verbose::echo2("Request to exchange: ", ModelExchangesRequestsStack::formatVerbose($request));

        if ($request['__inProgress']) {
            Verbose::echo2("Request stack [%s] is in progress with status [%s]", $request['id'], $request['status']);
            return;
        }

        $error = '';
        $exchangeOrderId = ClassAbstractExchangeApi::getApi($dOrder['exchangeId'])->parseResponseCreatedOrderTypeReplace(
            $request['status'], $request['responseBody'], $error, $dOrder
        );
        if (!$exchangeOrderId) {
            $this->_setDecomposedOrderAsFailed($dOrder['id'], $request['id'], $error);
            return;
        }

        Verbose::echo2("Order id at exchange: $exchangeOrderId");

        $this->_ordersDecomposedModel->setExchangeOrderId($dOrder['id'], $exchangeOrderId);

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($request['id']);

        $this->_ordersDecomposedModel->updateStatus(
            $dOrder['id'], $this->_ordersDecomposedModel::STATUS_DOING, $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATED
        );

        Model::inst()->commit();
    }

    private function _checkDecomposedOrderTypeNewCreated(array $dOrder)
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        ModelExchangesRequestsStack::pushRequestToBuffer($this->_buildRequestGetOrder($dOrder));

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::flushRequestsBuffer();

        $this->_ordersDecomposedModel->updateStatus(
            $dOrder['id'], $this->_ordersDecomposedModel::STATUS_DOING, $this->_ordersDecomposedModel::STATUS_CODE_DOING_STATE_WAIT_REQ
        );

        Model::inst()->commit();
    }

    private function _checkDecomposedOrderTypeReplaceCreated(array $dOrder)
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);
        $this->_checkDecomposedOrderTypeNewCreated($dOrder);
    }

    private function _checkDecomposedOrderTypeNewStatusWaitReq(array $dOrder)
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        $request = ModelExchangesRequestsStack::inst()->getAndValidateUnprocessedRequestByStrId($dOrder['requestStrId']);
        Verbose::echo2("Request stack id: ", $request['id']);
        Verbose::echo2("Request to exchange: ", $request);

        if ($request['__inProgress']) {
            Verbose::echo2("Request stack [%s] is in progress with status [%s]", $request['id'], $request['status']);
            return;
        }

        $error = '';
        $exchangeOrder = ClassAbstractExchangeApi::getApi($dOrder['exchangeId'])->parseResponseGetOrder(
            $request['status'], $request['responseBody'], $error, $dOrder
        );
        if (!$exchangeOrder) {
            $this->_setDecomposedOrderAsFailed($dOrder['id'], $request['id'], $error);
            return;
        }

        Verbose::echo2(
            "Decomposed order [%s] at exchange: status [%s] state: ", $dOrder['id'], $exchangeOrder['status'], $exchangeOrder
        );
        if ($exchangeOrder['status'] == $this->_ordersDecomposedModel::STATUS_DOING) {
            ModelExchangesRequestsStack::pushRequestToBuffer($this->_buildRequestGetOrder($dOrder));

            Model::inst()->beginTransaction();

            ModelExchangesRequestsStack::flushRequestsBuffer();

            ModelExchangesRequestsStack::inst()->setProcessedByRequester($request['id']);

            if ($dOrder['remain'] != $exchangeOrder['remain']) {
                $this->_ordersDecomposedModel->updateRemain($dOrder['id'], $exchangeOrder['remain']);
            }
            if ($dOrder['priceAvgExec'] != $exchangeOrder['priceAvgExec']) {
                $this->_ordersDecomposedModel->updatePriceAvgExec($dOrder['id'], $exchangeOrder['priceAvgExec']);
            }
            if ($dOrder['fee'] != $exchangeOrder['fee']) {
                $this->_ordersDecomposedModel->updateFee($dOrder['id'], $exchangeOrder['fee']);
            }

            $this->_ordersDecomposedModel->updateStatus(
                $dOrder['id'],
                $this->_ordersDecomposedModel::STATUS_DOING, $this->_ordersDecomposedModel::STATUS_CODE_DOING_STATE_WAIT_REQ, '', -1
            );

            Model::inst()->commit();
        }
        elseif (
            $exchangeOrder['status'] == $this->_ordersDecomposedModel::STATUS_COMPLETED
            || $exchangeOrder['status'] == $this->_ordersDecomposedModel::STATUS_REJECTED
        ) {
            if (
                $exchangeOrder['status'] == $this->_ordersDecomposedModel::STATUS_COMPLETED
                && $exchangeOrder['remain'] != 0
            ) {
                throw new ClassAdminOrderCheckDecomposedErr(
                    "Bad remain amount for order at exchange: Must be equal to zero for completed decomposed order: ", $exchangeOrder, $dOrder
                );
            }

            Model::inst()->beginTransaction();

            ModelExchangesRequestsStack::inst()->setProcessedByRequester($request['id']);

            if ($dOrder['remain'] != $exchangeOrder['remain']) {
                $this->_ordersDecomposedModel->updateRemain($dOrder['id'], $exchangeOrder['remain']);
            }
            if ($dOrder['priceAvgExec'] != $exchangeOrder['priceAvgExec']) {
                $this->_ordersDecomposedModel->updatePriceAvgExec($dOrder['id'], $exchangeOrder['priceAvgExec']);
            }
            if ($dOrder['fee'] != $exchangeOrder['fee']) {
                $this->_ordersDecomposedModel->updateFee($dOrder['id'], $exchangeOrder['fee']);
            }

            $this->_ordersDecomposedModel->updateStatus($dOrder['id'], $exchangeOrder['status'], $exchangeOrder['statusCode']);

            Model::inst()->commit();
        }
        else {
            throw new ClassAdminOrderCheckDecomposedErr("Bad status: ", $exchangeOrder);
        }
    }

    private function _checkDecomposedOrderTypeReplaceStatusWaitReq(array $dOrder)
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);
        $this->_checkDecomposedOrderTypeNewStatusWaitReq($dOrder);
    }

    private function _checkDecomposedOrderTypeCancel(array $dOrder)
    {
        Verbose::echo2("Doing [%s]", __FUNCTION__);

        $request = ModelExchangesRequestsStack::inst()->getAndValidateUnprocessedRequestByStrId($dOrder['requestStrId']);
        Verbose::echo2("Request stack id: ", $request['id']);
        Verbose::echo2("Request to exchange: ", ModelExchangesRequestsStack::formatVerbose($request));

        if ($request['__inProgress']) {
            Verbose::echo2("Request stack [%s] is in progress with status [%s]", $request['id'], $request['status']);
            return;
        }

        $error = '';
        $isCancelled = ClassAbstractExchangeApi::getApi($dOrder['exchangeId'])->parseResponseCreatedOrderTypeCancel(
            $request['status'], $request['responseBody'], $error, $dOrder
        );
        if (!$isCancelled) {
            $this->_setDecomposedOrderAsFailed($dOrder['id'], $request['id'], $error);
            return;
        }

        Verbose::echo2("Order is canceled at exchange");

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($request['id']);

        $this->_ordersDecomposedModel->updateStatus(
            $dOrder['id'], $this->_ordersDecomposedModel::STATUS_COMPLETED, $this->_ordersDecomposedModel::STATUS_CODE_COMPLETED
        );

        Model::inst()->commit();
    }

    protected function _validateDecomposedOrderTypeNew(array $dOrder)
    {
        $this->_validateDecomposedOrder($dOrder);
        if (
            $dOrder['statusCode'] == $this->_ordersDecomposedModel::STATUS_CODE_DOING_CANCELLED
            || ($dOrder['statusCode'] == $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ
                && $dOrder['exchangeOrderId'])
            || (in_array($dOrder['statusCode'], [
                    $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATED,
                    $this->_ordersDecomposedModel::STATUS_CODE_DOING_CANCELLED,
                    $this->_ordersDecomposedModel::STATUS_CODE_DOING_STATE_WAIT_REQ
                ])
                && !$dOrder['exchangeOrderId'])
            || !$dOrder['currPairId']
            || $dOrder['amount'] < 0
            || $dOrder['remain'] < 0
            || $dOrder['price'] <= 0
            || !in_array($dOrder['side'], $this->_ordersDecomposedModel::getSides())
            || !in_array($dOrder['exec'], $this->_ordersDecomposedModel::getExecs())
        ) {
            throw new ClassAdminOrderCheckDecomposedErr("Bad decomposed order: ", $dOrder);
        }
    }

    protected function _validateDecomposedOrderTypeReplace(array $dOrder)
    {
        $this->_validateDecomposedOrder($dOrder);
        $this->_validateDecomposedOrderTypeNew($dOrder);
        if (!$dOrder['replaceOrderDecomposedId']) {
            throw new ClassAdminOrderCheckDecomposedErr("Bad decomposed order: ", $dOrder);
        }
    }

    protected function _validateDecomposedOrderTypeCancel(array $dOrder)
    {
        $this->_validateDecomposedOrder($dOrder);
        if (
            !$dOrder['cancelOrderDecomposedId']
            || $dOrder['statusCode'] == $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATED
        ) {
            throw new ClassAdminOrderCheckDecomposedErr("Bad decomposed order: ", $dOrder);
        }
    }

    protected function _validateDecomposedOrder(array $dOrder)
    {
        if (
            !$dOrder['id']
            || $dOrder['orderId'] != $this->_order['id']
            || !$dOrder['exchangeId']
            || ((bool) $dOrder['systemApiKeyId'] + (bool) $dOrder['userApiKeyId'] != 1)
            || !$dOrder['requestStrId']
            || $dOrder['status'] != $this->_ordersDecomposedModel::STATUS_DOING
            || !in_array($dOrder['statusCode'], [
                $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ,
                $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATED,
                $this->_ordersDecomposedModel::STATUS_CODE_DOING_CANCELLED,
                $this->_ordersDecomposedModel::STATUS_CODE_DOING_STATE_WAIT_REQ
            ])
            || !$dOrder['enabled']
        ) {
            throw new ClassAdminOrderCheckDecomposedErr("Bad decomposed order: ", $dOrder);
        }
    }

    private function _buildRequestGetOrder(array $dOrder): array
    {
        $request = ClassAbstractExchangeApi::getApi($dOrder['exchangeId'])->buildRequestGetOrder($dOrder['exchangeOrderId']);
        if (!$request) {
            throw new ClassAdminOrderCheckDecomposedErr("Failed to build get order status request: ", $dOrder);
        }
        Verbose::echo2("Get order state request built: ", $request);

        return [
            'strId'             => $dOrder['requestStrId'],
            'groupStrId'        => $dOrder['requestGroupStrId'],
            'systemApiKeyId'    => $dOrder['systemApiKeyId'],
            'userApiKeyId'      => $dOrder['userApiKeyId'],
            'exchangeId'        => $dOrder['exchangeId'],
            'requesterType'     => $this->_ordersDecomposedModel::REQUESTER_TYPE,
            'requestUrl'        => $request['url'],
            'requestMethod'     => $request['method'],
            'requestHeaders'    => Json::encode($request['headers']),
            'requestData'       => Json::encode($request['data']),
            'requestNonce'      => $request['nonce'],
        ];
    }

    protected function _setDecomposedOrderAsFailed(
        int $dOrderId, int $requestId, string $error, int $statusCode = ModelAbstractAdminsOrdersDecomposed::STATUS_CODE_FAILED
    )
    {
        Verbose::echo2("FAILED: Decomposed order [$dOrderId] request [$requestId]: ", $error);

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($requestId);

        $this->_ordersDecomposedModel->updateStatus(
            $dOrderId, $this->_ordersDecomposedModel::STATUS_FAILED, $statusCode, $error
        );

        Model::inst()->commit();
    }

    protected function _updateOrderByDecomposedOrders()
    {
        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo2("Updating order [%s] by decomposed orders", $this->_order['id']);

        $dOrders = $this->_ordersDecomposedModel->getOrdersByOrderId($this->_order['id'], Model::LIMIT_MAX);
        if (!$dOrders) {
            throw new ClassAdminOrderCheckDecomposedErr("Failed to get decomposed orders");
        }
        $dOrdersTotal = count($dOrders);

        $this->_validateOrderStats($dOrdersTotal);

        if (in_array($this->_order['type'], [ModelAdminsOrders::TYPE_NEW, ModelAdminsOrders::TYPE_REPLACE])) {
            $orderRemain        = $this->_getOrderRemainByDecomposedOrders($dOrders);
            $orderPriceAvgExec  = $this->_getOrderPriceAvgExecByDecomposedOrders($dOrders);
            $orderFee           = $this->_getOrderFeeByDecomposedOrders($dOrders);
        }

        $dOrdersStatuses = $this->_getDecomposedOrdersStatuses($dOrders);
        Verbose::echo2("Decomposed orders statuses: ", $dOrdersStatuses);

        $dOrdersStatusCodes = $this->_getDecomposedOrdersStatusCodes($dOrders);
        Verbose::echo2("Decomposed orders status codes: ", $dOrdersStatusCodes);

        $orderStatusMsg = '';
        $orderStatus = $this->_getOrderStatusByDecomposedOrdersStatuses($dOrdersStatuses, $dOrdersTotal, $orderStatusMsg);
        Verbose::echo2("Order status: $orderStatus");
        Verbose::echo2("Order status msg: $orderStatusMsg");

        $orderStatusCode = $this->_getOrderStatusCodeByDecomposedOrdersStatusCodes($dOrdersStatusCodes, $dOrdersTotal);
        Verbose::echo2("Order status code: $orderStatusCode");

        $orderStats = [
            'ordersDecomposedDoing'     => $dOrdersStatuses[$this->_ordersDecomposedModel::STATUS_DOING],
            'ordersDecomposedCompleted' => $dOrdersStatuses[$this->_ordersDecomposedModel::STATUS_COMPLETED],
            'ordersDecomposedRejected'  => $dOrdersStatuses[$this->_ordersDecomposedModel::STATUS_REJECTED],
            'ordersDecomposedFailed'    => $dOrdersStatuses[$this->_ordersDecomposedModel::STATUS_FAILED],
            'ordersDecomposedSpecial'   => $dOrdersStatuses[$this->_ordersDecomposedModel::STATUS_SPECIAL],
        ];

        Model::inst()->beginTransaction();

        if (in_array($this->_order['type'], [ModelAdminsOrders::TYPE_NEW, ModelAdminsOrders::TYPE_REPLACE])) {
            if ($this->_order['remain'] != $orderRemain) {
                ModelAdminsOrders::inst()->updateRemain($this->_order['id'], $this->_order['type'], $orderRemain);
            }
            if ($this->_order['priceAvgExec'] != $orderPriceAvgExec) {
                ModelAdminsOrders::inst()->updatePriceAvgExec($this->_order['id'], $this->_order['type'], $orderPriceAvgExec);
            }
            if ($this->_order['fee'] != $orderFee) {
                ModelAdminsOrders::inst()->updateFee($this->_order['id'], $this->_order['type'], $orderFee);
            }
        }

        ModelAdminsOrders::inst()->updateStats(
            $this->_order['id'], $orderStats
        );
        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'], $orderStatus, $orderStatusCode, $orderStatusMsg
        );

        Model::inst()->commit();
    }

    private function _validateOrderStats(int $dOrdersTotal)
    {
        $orderStats = ModelAdminsOrders::inst()->getOrderStatsById($this->_order['id']);
        if (!$orderStats) {
            throw new ClassAdminOrderCheckDecomposedErr("Failed to get order stats");
        }
        if ($orderStats['ordersDecomposedTotal'] != $dOrdersTotal) {
            throw new ClassAdminOrderCheckDecomposedErr(
                "Bad order stats [ordersDecomposedTotal] [%s] != [$dOrdersTotal]", $orderStats['ordersDecomposedTotal']
            );
        }
        if ($orderStats['ordersDecomposedNew']) {
            throw new ClassAdminOrderCheckDecomposedErr(
                "Bad order stats [ordersDecomposedNew] [%s] != [0]", $orderStats['ordersDecomposedNew']
            );
        }
    }

    private function _getOrderRemainByDecomposedOrders(array $dOrders): float
    {
        $amountExchanged = 0;
        foreach ($dOrders as $dOrder) {
            $amountExchanged += ($dOrder['amount'] - $dOrder['remain']);
        }
        $remain = $this->_order['remain'] - $amountExchanged;
        if (
            // Disabled: Can be negative
            //$remain < 0 ||
            $remain > $this->_order['amount']
        ) {
            throw new ClassAdminOrderCheckDecomposedErr("Bad order remain [$remain]");
        }
        return $remain;
    }

    private function _getOrderPriceAvgExecByDecomposedOrders(array $dOrders): float
    {
        $amountDoneSum = 0;
        $priceAvgExecSum = 0;
        $priceAvgExec = 0;
        foreach ($dOrders as $dOrder) {
            $amountDone = $dOrder['amount'] - $dOrder['remain'];
            $amountDoneSum += $amountDone;
            $priceAvgExecSum += ($amountDone * $dOrder['priceAvgExec']);
        }
        if ($amountDoneSum) {
            $priceAvgExec = NumFloat::floor($priceAvgExecSum / $amountDoneSum);
            if (
                $this->_order['exec'] != ModelAdminsOrders::EXEC_MARKET
                && ($priceAvgExec < $this->_order['price'] * 0.8 || $priceAvgExec > $this->_order['price'] * 1.2)
            ) {
                throw new ClassAdminOrderCheckDecomposedErr("Order average execution price [$priceAvgExec] is too low or too high");
            }
        }
        return $priceAvgExec;
    }

    private function _getOrderFeeByDecomposedOrders(array $dOrders): float
    {
        $feeSum = 0;
        foreach ($dOrders as $dOrder) {
            $feeSum += $dOrder['fee'];
        }
        if ($feeSum < 0 || $feeSum >= $this->_order['amount']) {
            throw new ClassAdminOrderCheckDecomposedErr("Bad order fee sum [$feeSum]");
        }
        return $feeSum;
    }

    private function _getDecomposedOrdersStatuses(array $dOrders): array
    {
        $statuses = [];
        foreach ($this->_ordersDecomposedModel::getStatuses() as $status) {
            $statuses[$status] = 0;
        }
        foreach ($dOrders as $dOrder) {
            if (!isset($statuses[$dOrder['status']])) {
                throw new ClassAdminOrderCheckDecomposedErr("Bad status [%s] of decomposed order [%s]", $dOrder['status'], $dOrder['id']);
            }

            if ($dOrder['status'] == $this->_ordersDecomposedModel::STATUS_NEW) {
                throw new ClassAdminOrderCheckDecomposedErr("Unexpected status [%s] of decomposed order [%s]", $dOrder['status'], $dOrder['id']);
            }
            elseif ($dOrder['status'] == $this->_ordersDecomposedModel::STATUS_SPECIAL) {
                throw new ClassAdminOrderCheckDecomposedErr("Don't know how to use status [%s] of decomposed order [%s]", $dOrder['status'], $dOrder['id']);
            }

            $statuses[$dOrder['status']] += 1;
        }
        return $statuses;
    }

    private function _getDecomposedOrdersStatusCodes(array $dOrders): array
    {
        $statusCodes = [];
        foreach ($this->_ordersDecomposedModel::getStatusCodes() as $code) {
            $statusCodes[$code] = 0;
        }
        foreach ($dOrders as $dOrder) {
            if (!isset($statusCodes[$dOrder['statusCode']])) {
                throw new ClassAdminOrderCheckDecomposedErr("Bad status code [%s] of decomposed order [%s]", $dOrder['statusCode'], $dOrder['id']);
            }

            if ($dOrder['statusCode'] == $this->_ordersDecomposedModel::STATUS_CODE_NEW) {
                throw new ClassAdminOrderCheckDecomposedErr("Unexpected status code [%s] of decomposed order [%s]", $dOrder['statusCode'], $dOrder['id']);
            }
            elseif (in_array($dOrder['statusCode'], $this->_ordersDecomposedModel::getStatusCodesSpecial())) {
                throw new ClassAdminOrderCheckDecomposedErr(
                    "Don't know how to use status code [%s] of decomposed order [%s]", $dOrder['statusCode'], $dOrder['id']
                );
            }

            $statusCodes[$dOrder['statusCode']] += 1;
        }
        return $statusCodes;
    }

    private function _getOrderStatusByDecomposedOrdersStatuses(array $dOrdersStatuses, int $dOrdersTotal, string &$orderStatusMsg): string
    {
        // All decomposed orders statuses are equal
        foreach ($dOrdersStatuses as $status => $count) {
            if ($count == $dOrdersTotal) {
                $orderStatusMsg = "Equal: all decomposed orders have equal status [$status]";
                return $status;
            }
        }

        if ($dOrdersStatuses[$this->_ordersDecomposedModel::STATUS_FAILED] > 0) {
            $orderStatusMsg = "Failed: at least one decomposed order is failed";
            return ModelAdminsOrders::STATUS_FAILED;
        }
        if ($dOrdersStatuses[$this->_ordersDecomposedModel::STATUS_REJECTED] > 0) {
            $orderStatusMsg = "Rejected: at least one decomposed order is rejected";
            return ModelAdminsOrders::STATUS_REJECTED;
        }
        if ($dOrdersStatuses[$this->_ordersDecomposedModel::STATUS_DOING] > 0) {
            $orderStatusMsg = "Doing: at least one decomposed order is doing";
            return ModelAdminsOrders::STATUS_DOING;
        }
        throw new ClassAdminOrderCheckDecomposedErr("Bad decomposed orders statuses: ", $dOrdersStatuses);
    }

    private function _getOrderStatusCodeByDecomposedOrdersStatusCodes(array $dOrdersStatusCodes, int $dOrdersTotal): int
    {
        // All decomposed orders status codes are equal
        foreach ($dOrdersStatusCodes as $code => $count) {
            if ($count == $dOrdersTotal) {
                return $code;
            }
        }

        foreach ($this->_ordersDecomposedModel::getStatusCodesFailed() as $code) {
            if ($dOrdersStatusCodes[$code] > 0) {
                return ModelAdminsOrders::STATUS_CODE_FAILED;
            }
        }
        foreach ($this->_ordersDecomposedModel::getStatusCodesRejected() as $code) {
            if ($dOrdersStatusCodes[$code] > 0) {
                return ModelAdminsOrders::STATUS_CODE_REJECTED;
            }
        }
        if ($dOrdersStatusCodes[$this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_BUILD_REQ] > 0) {
            return ModelAdminsOrders::STATUS_CODE_DOING_CREATE_BUILD_REQ;
        }
        if ($dOrdersStatusCodes[$this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ] > 0) {
            return ModelAdminsOrders::STATUS_CODE_DOING_CREATE_WAIT_REQ;
        }
        if ($dOrdersStatusCodes[$this->_ordersDecomposedModel::STATUS_CODE_DOING_STATE_BUILD_REQ] > 0) {
            return ModelAdminsOrders::STATUS_CODE_DOING_STATE_BUILD_REQ;
        }
        if ($dOrdersStatusCodes[$this->_ordersDecomposedModel::STATUS_CODE_DOING_STATE_WAIT_REQ] > 0) {
            return ModelAdminsOrders::STATUS_CODE_DOING_STATE_WAIT_REQ;
        }
        throw new ClassAdminOrderCheckDecomposedErr("Bad decomposed orders status codes: ", $dOrdersStatusCodes);
    }
}