<?php
/**
 * Create decomposed orders at exchanges
 */
class ClassAdminOrderCreateDecomposed
{
    private $_order = [];
    private $_ordersDecomposed = [];
    private $_ordersDecomposedModel;

    function __construct(int $orderId)
    {
        ClassAdminOrderCreateDecomposedErr::set($orderId);

        $order = ModelAdminsOrders::inst()->getOrderAndDataById($orderId);
        if (!$order) {
            throw new ClassAdminOrderCreateDecomposedErr("Failed to get order");
        }
        if (
            !in_array($order['type'], ModelAdminsOrders::getTypes())
            || $order['status'] != ModelAdminsOrders::STATUS_DOING
            || $order['statusCode'] != ModelAdminsOrders::STATUS_CODE_DOING_APPROVED
            || !$order['approvedAdminId']
            || !$order['enabled']
        ) {
            throw new ClassAdminOrderCreateDecomposedErr("Bad order: ", $order);
        }
        $this->_order = $order;

        $this->_ordersDecomposedModel = ModelAbstractAdminsOrdersDecomposed::getModel($order['type']);

        $dOrders = $this->_ordersDecomposedModel->getActiveOrdersByOrderIdAndStatusCodes(
            $orderId, [$this->_ordersDecomposedModel::STATUS_CODE_NEW], Model::LIMIT_MAX
        );
        if (!$dOrders) {
            throw new ClassAdminOrderCreateDecomposedErr("Failed to get active decomposed orders");
        }
        $this->_ordersDecomposed = $dOrders;
    }

    function run()
    {
        Verbose::echo1("Creating order [%s] type [%s] decomposed orders at exchanges", $this->_order['id'], $this->_order['type']);
        Verbose::echo2("Order: ", $this->_order);

        switch ($this->_order['type']) {
            case ModelAdminsOrders::TYPE_NEW:
                $this->_createDecomposedOrdersTypeNew();
                break;
            case ModelAdminsOrders::TYPE_REPLACE:
                $this->_createDecomposedOrdersTypeReplace();
                break;
            case ModelAdminsOrders::TYPE_CANCEL:
                $this->_createDecomposedOrdersTypeCancel();
                break;
            default:
                throw new ClassAdminOrderCreateDecomposedErr("Bad condition");
        }
    }

    private function _createDecomposedOrdersTypeNew()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'],
            ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_CREATE_BUILD_REQ
        );
        // NOTE: Skipped setting same status for decomposed orders as it is not necessary

        $this->_validateDecomposedOrdersTypeNew();

        foreach ($this->_ordersDecomposed as $dOrder) {
            Verbose::echo2("Building request for order [%s] decomposed order [%s]", $this->_order['id'], $dOrder['id']);
            Verbose::echo2("Decomposed order: ", $dOrder);

            $exchangeApi = ClassAbstractExchangeApi::getApi($dOrder['exchangeId']);
            $request = $exchangeApi->buildRequestCreateOrderTypeNew(
                $dOrder['currPairId'], $dOrder['amount'], $dOrder['price'], $dOrder['side'], $dOrder['exec']
            );
            if (!$request) {
                throw new ClassAdminOrderCreateDecomposedErr("Failed to build create order request: ", $dOrder);
            }

            ModelExchangesRequestsStack::pushRequestToBuffer([
                'strId'             => $dOrder['requestStrId'],
                'groupStrId'        => $this->_ordersDecomposed[0]['requestGroupStrId'],
                'exchangeId'        => $dOrder['exchangeId'],
                'systemApiKeyId'    => $dOrder['systemApiKeyId'],
                'userApiKeyId'      => $dOrder['userApiKeyId'],
                'requesterType'     => $this->_ordersDecomposedModel::REQUESTER_TYPE,
                'requestUrl'        => $request['url'],
                'requestMethod'     => $request['method'],
                'requestHeaders'    => Json::encode($request['headers']),
                'requestData'       => Json::encode($request['data']),
                'requestNonce'      => $request['nonce'],
            ]);
        }
        Verbose::echo2("Total requests built: ", ModelExchangesRequestsStack::getRequestsBufferLength());

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::flushRequestsBuffer();

        $this->_ordersDecomposedModel->updateStatusesByOrderId(
            $this->_order['id'],
            $this->_ordersDecomposedModel::STATUS_DOING, $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ
        );

        ModelAdminsOrders::inst()->updateStats(
            $this->_order['id'], ['ordersDecomposedNew' => 0, 'ordersDecomposedDoing' => count($this->_ordersDecomposed)]
        );
        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'], ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_CREATE_WAIT_REQ
        );

        Model::inst()->commit();
    }

    private function _createDecomposedOrdersTypeReplace()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'],
            ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_CREATE_BUILD_REQ
        );
        // NOTE: Skipped setting same status for decomposed orders as it is not necessary

        $this->_validateDecomposedOrdersTypeReplace();

        $orderToReplace = ModelAdminsOrders::inst()->getOrderById($this->_order['replaceOrderId']);
        if (!$orderToReplace) {
            throw new ClassAdminOrderCreateDecomposedErr("Failed to get replace order [%s]", $this->_order['replaceOrderId']);
        }

        $mDecomposedOrdersToReplace = ModelAbstractAdminsOrdersDecomposed::getModel($orderToReplace['type']);

        foreach ($this->_ordersDecomposed as $dOrder) {
            Verbose::echo2("Building request for order [%s] decomposed order [%s]", $this->_order['id'], $dOrder['id']);
            Verbose::echo2("Decomposed order: ", $dOrder);

            $orderToReplace = $mDecomposedOrdersToReplace->getOrderById($dOrder['replaceOrderDecomposedId'], ['exchangeOrderId']);
            if (!$orderToReplace) {
                throw new ClassAdminOrderCreateDecomposedErr("Failed to get replace decomposed order [%s]", $dOrder['replaceOrderDecomposedId']);
            }
            if (!$orderToReplace['exchangeOrderId']) {
                throw new ClassAdminOrderCreateDecomposedErr("Empty replace decomposed order [%s] exchange order id", $dOrder['replaceOrderDecomposedId']);
            }
            Verbose::echo2("Decomposed order to replace exchange order id: ", $orderToReplace['exchangeOrderId']);

            $exchangeApi = ClassAbstractExchangeApi::getApi($dOrder['exchangeId']);
            $request = $exchangeApi->buildRequestCreateOrderTypeReplace(
                $orderToReplace['exchangeOrderId'], $dOrder['currPairId'], $dOrder['amount'], $dOrder['price'], $dOrder['side'], $dOrder['exec']
            );
            if (!$request) {
                throw new ClassAdminOrderCreateDecomposedErr("Failed to build create order request: ", $dOrder);
            }

            ModelExchangesRequestsStack::pushRequestToBuffer([
                'strId'             => $dOrder['requestStrId'],
                'groupStrId'        => $this->_ordersDecomposed[0]['requestGroupStrId'],
                'exchangeId'        => $dOrder['exchangeId'],
                'systemApiKeyId'    => $dOrder['systemApiKeyId'],
                'userApiKeyId'      => $dOrder['userApiKeyId'],
                'requesterType'     => $this->_ordersDecomposedModel::REQUESTER_TYPE,
                'requestUrl'        => $request['url'],
                'requestMethod'     => $request['method'],
                'requestHeaders'    => Json::encode($request['headers']),
                'requestData'       => Json::encode($request['data']),
                'requestNonce'      => $request['nonce'],
            ]);
        }
        Verbose::echo2("Total requests built: ", ModelExchangesRequestsStack::getRequestsBufferLength());

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::flushRequestsBuffer();

        $this->_ordersDecomposedModel->updateStatusesByOrderId(
            $this->_order['id'],
            $this->_ordersDecomposedModel::STATUS_DOING, $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ
        );

        ModelAdminsOrders::inst()->updateStats(
            $this->_order['id'], ['ordersDecomposedNew' => 0, 'ordersDecomposedDoing' => count($this->_ordersDecomposed)]
        );
        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'], ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_CREATE_WAIT_REQ
        );

        Model::inst()->commit();
    }

    private function _createDecomposedOrdersTypeCancel()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        ModelAdminsOrders::inst()->updateStatus(
            $this->_order['id'],
            ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_CREATE_BUILD_REQ
        );
        // NOTE: Skipped setting same status for decomposed orders as it is not necessary

        $this->_validateDecomposedOrdersTypeCancel();

        $orderToCancel = ModelAdminsOrders::inst()->getOrderById($this->_order['cancelOrderId']);
        if (!$orderToCancel) {
            throw new ClassAdminOrderCreateDecomposedErr("Failed to get cancel order [%s]", $this->_order['cancelOrderId']);
        }

        $mDecomposedOrdersToCancel = ModelAbstractAdminsOrdersDecomposed::getModel($orderToCancel['type']);

        $existOrdersCreatedAtExchange = false;
        foreach ($this->_ordersDecomposed as $dOrder) {
            Verbose::echo2("Building request for order [%s] decomposed order [%s]", $this->_order['id'], $dOrder['id']);
            Verbose::echo2("Decomposed order: ", $dOrder);

            $dOrderToCancel = $mDecomposedOrdersToCancel->getOrderById($dOrder['cancelOrderDecomposedId'], ['exchangeOrderId']);
            if (!$dOrderToCancel) {
                throw new ClassAdminOrderCreateDecomposedErr("Failed to get cancel decomposed order [%s]", $dOrder['cancelOrderDecomposedId']);
            }
            if (!$dOrderToCancel['exchangeOrderId']) {
                Verbose::echo2("Skip: Decomposed order to cancel has empty exchange order id");
                continue;
            }
            Verbose::echo2("Decomposed order to cancel exchange order id: ", $dOrderToCancel['exchangeOrderId']);

            $exchangeApi = ClassAbstractExchangeApi::getApi($dOrder['exchangeId']);
            $request = $exchangeApi->buildRequestCreateOrderTypeCancel($dOrderToCancel['exchangeOrderId']);
            if (!$request) {
                throw new ClassAdminOrderCreateDecomposedErr("Failed to build create order request: ", $dOrder);
            }

            ModelExchangesRequestsStack::pushRequestToBuffer([
                'strId'             => $dOrder['requestStrId'],
                'groupStrId'        => $this->_ordersDecomposed[0]['requestGroupStrId'],
                'exchangeId'        => $dOrder['exchangeId'],
                'systemApiKeyId'    => $dOrder['systemApiKeyId'],
                'userApiKeyId'      => $dOrder['userApiKeyId'],
                'requesterType'     => $this->_ordersDecomposedModel::REQUESTER_TYPE,
                'requestUrl'        => $request['url'],
                'requestMethod'     => $request['method'],
                'requestHeaders'    => Json::encode($request['headers']),
                'requestData'       => Json::encode($request['data']),
                'requestNonce'      => $request['nonce'],
            ]);
            $existOrdersCreatedAtExchange = true;
        }
        Verbose::echo2("Total requests built: ", ModelExchangesRequestsStack::getRequestsBufferLength());

        if ($existOrdersCreatedAtExchange) {
            Model::inst()->beginTransaction();

            ModelExchangesRequestsStack::flushRequestsBuffer();

            $this->_ordersDecomposedModel->updateStatusesByOrderId(
                $this->_order['id'], $this->_ordersDecomposedModel::STATUS_DOING, $this->_ordersDecomposedModel::STATUS_CODE_DOING_CREATE_WAIT_REQ
            );

            ModelAdminsOrders::inst()->updateStats(
                $this->_order['id'], ['ordersDecomposedNew' => 0, 'ordersDecomposedDoing' => count($this->_ordersDecomposed)]
            );
            ModelAdminsOrders::inst()->updateStatus(
                $this->_order['id'], ModelAdminsOrders::STATUS_DOING, ModelAdminsOrders::STATUS_CODE_DOING_CREATE_WAIT_REQ
            );

            Model::inst()->commit();
        }
        else {
            Verbose::echo2(
                "Cancel order is completed: No decomposed orders that need to be cancelled were created at exchanges"
            );
            ModelAdminsOrders::inst()->updateStatus(
                $this->_order['id'], ModelAdminsOrders::STATUS_COMPLETED, ModelAdminsOrders::STATUS_CODE_COMPLETED
            );
        }
    }

    private function _validateDecomposedOrdersTypeNew()
    {
        foreach ($this->_ordersDecomposed as $dOrder) {
            $this->_validateDecomposedOrderTypeNew($dOrder);
        }
        $this->_validateRequestGroupStrId();
    }

    private function _validateDecomposedOrdersTypeReplace()
    {
        foreach ($this->_ordersDecomposed as $dOrder) {
            $this->_validateDecomposedOrderTypeReplace($dOrder);
        }
        $this->_validateRequestGroupStrId();
    }

    private function _validateDecomposedOrdersTypeCancel()
    {
        foreach ($this->_ordersDecomposed as $dOrder) {
            $this->_validateDecomposedOrderTypeCancel($dOrder);
        }
        $this->_validateRequestGroupStrId();
    }

    private function _validateRequestGroupStrId()
    {
        $requestGroupStrId = '';
        foreach ($this->_ordersDecomposed as $dOrder) {
            if (!$requestGroupStrId) {
                $requestGroupStrId = $dOrder['requestGroupStrId'];
            }
            if ($requestGroupStrId != $dOrder['requestGroupStrId']) {
                throw new ClassAdminOrderCreateDecomposedErr(
                    "Decomposed orders have not equal requestGroupStrId [$requestGroupStrId, %s]", $dOrder['requestGroupStrId']
                );
            }
        }
    }

    private function _validateDecomposedOrder(array $dOrder)
    {
        if (
            !$dOrder['id']
            || $dOrder['orderId'] != $this->_order['id']
            || !$dOrder['exchangeId']
            || !ModelAbstractApiKeys::checkIds($dOrder['systemApiKeyId'], $dOrder['userApiKeyId'], false)
            || !$dOrder['requestStrId']
            || !$dOrder['requestGroupStrId']
            || $dOrder['status'] != $this->_ordersDecomposedModel::STATUS_NEW
            || $dOrder['statusCode'] != $this->_ordersDecomposedModel::STATUS_CODE_NEW
            || !$dOrder['enabled']
        ) {
            throw new ClassAdminOrderCreateDecomposedErr("Bad decomposed order: ", $dOrder);
        }
    }

    private function _validateDecomposedOrderTypeNew(array $dOrder)
    {
        $this->_validateDecomposedOrder($dOrder);
        if (
            $dOrder['exchangeOrderId']
            || !$dOrder['currPairId']
            || $dOrder['amount'] <= 0
            || $dOrder['remain'] != $dOrder['amount']
            || $dOrder['price'] <= 0
            || !in_array($dOrder['side'], $this->_ordersDecomposedModel::getSides())
            || !in_array($dOrder['exec'], $this->_ordersDecomposedModel::getExecs())
        ) {
            throw new ClassAdminOrderCreateDecomposedErr("Bad decomposed order: ", $dOrder);
        }
    }

    private function _validateDecomposedOrderTypeReplace(array $dOrder)
    {
        $this->_validateDecomposedOrder($dOrder);
        $this->_validateDecomposedOrderTypeNew($dOrder);
        if (!$dOrder['replaceOrderDecomposedId']) {
            throw new ClassAdminOrderCreateDecomposedErr("Bad decomposed order: ", $dOrder);
        }
    }

    private function _validateDecomposedOrderTypeCancel(array $dOrder)
    {
        $this->_validateDecomposedOrder($dOrder);
        if (!$dOrder['cancelOrderDecomposedId']) {
            throw new ClassAdminOrderCreateDecomposedErr("Bad decomposed order: ", $dOrder);
        }
    }
}