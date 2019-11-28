<?php
/**
 *
 */
class ClassCronAdminsOrdersProcess extends ClassAbstractCron
{
    const ORDER_CHECK_LOCK_SEC = 15;

    const E_FAILED               = 2000000;
    const E_NO_ACCOUNTS_SELECTED = 2000001;
    const E_NO_DECOMPOSED_ORDERS = 2000002;

    private static $_ordersCheckLock = [];

    function run(int $doStatusCode = -1)
    {
        Verbose::echo1("Processing admin orders");

        $doStatusCodes = [
            ModelAdminsOrders::STATUS_CODE_NEW                      => ['ClassAdminOrderDecompose', false],
            ModelAdminsOrders::STATUS_CODE_DOING_APPROVED           => ['ClassAdminOrderCreateDecomposed', false],
            ModelAdminsOrders::STATUS_CODE_DOING_CREATE_WAIT_REQ    => ['ClassAdminOrderCheckDecomposed', false],
            ModelAdminsOrders::STATUS_CODE_DOING_CREATED            => ['ClassAdminOrderCheckDecomposed', true],
            ModelAdminsOrders::STATUS_CODE_DOING_CANCELLED          => ['ClassAdminOrderCheckDecomposed', true],
            ModelAdminsOrders::STATUS_CODE_DOING_STATE_WAIT_REQ     => ['ClassAdminOrderCheckDecomposed', true],
        ];

        if ($doStatusCode != -1) {
            if (!isset($doStatusCodes[$doStatusCode])) {
                throw new Err("Bad order status code [$doStatusCode]");
            }
            $tmp[$doStatusCode] = $doStatusCodes[$doStatusCode];
            $doStatusCodes = $tmp;
            unset($tmp);
        }

        $mAdminsOrders = ModelAdminsOrders::inst();
        $ordersTotal = 0;
        foreach ($doStatusCodes as $statusCode => $action) {
            Verbose::echo2(Verbose::EMPTY_LINE);
            Verbose::echo1("Doing orders with status code [$statusCode:%s]", ModelAdminsOrders::verboseStatusCode($statusCode));

            $orders = $mAdminsOrders->getActivePriorityOrdersByStatusCode($statusCode, Model::LIMIT_MAX, ['id', 'type']);
            if ($orders) {
                Verbose::echo1("Orders to process: ", count($orders));
                foreach ($orders as $order) {
                    try {
                        Verbose::echo2(Verbose::EMPTY_LINE);
                        Verbose::echo1("Doing order [%s]", $order['id']);
                        $ordersTotal++;

                        // Order can be active on select, but then cancelled or replaced, so it can be inactive here
                        if (!$mAdminsOrders->isOrderActiveById($order['id'])) {
                            Verbose::echo1("Skip: Order is inactive");
                            continue;
                        }

                        list($classAdminOrder, $applyOrderCheckLock) = $action;
                        if ($applyOrderCheckLock) {
                            $checkLockId = $order['id'];
                            if (!isset(self::$_ordersCheckLock[$checkLockId]) || self::$_ordersCheckLock[$checkLockId] <= time()) {
                                (new $classAdminOrder($order['id']))->run();
                                self::$_ordersCheckLock[$checkLockId] = time() + self::ORDER_CHECK_LOCK_SEC;
                            }
                            else {
                                Verbose::echo1("Skip: Check is locked till [%s]", ClassDateTime::dbDateTime(self::$_ordersCheckLock[$checkLockId]));
                            }
                        }
                        else {
                            (new $classAdminOrder($order['id']))->run();
                        }
                    }
                    catch (Exception $e) {
                        $this->_handleError($e, $order);
                        if (!empty($applyOrderCheckLock) && !empty($checkLockId)) {
                            self::$_ordersCheckLock[$checkLockId] = time() + self::ORDER_CHECK_LOCK_SEC;
                        }
                    }
                }
            }
            else {
                Verbose::echo1("No active orders found");
            }
        }

        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo1("Orders processed: $ordersTotal");
        Verbose::echo1("Errors count: $this->_errorsCount");
    }

    private function _handleError(Exception $e, array $order)
    {
        $errCodesMap = [
            self::E_FAILED => [
                'statusCode' => ModelAdminsOrders::STATUS_CODE_FAILED,
                'fatal' => true,
            ],
            self::E_NO_ACCOUNTS_SELECTED => [
                'statusCode' => ModelAdminsOrders::STATUS_CODE_FAILED_NO_ACCOUNTS_SELECTED,
                'fatal' => false,
            ],
            self::E_NO_DECOMPOSED_ORDERS => [
                'statusCode' => ModelAdminsOrders::STATUS_CODE_FAILED_NO_DECOMPOSED_ORDERS,
                'fatal' => false,
            ],
        ];

        $errCode = $e->getCode();
        if (isset($errCodesMap[$errCode])) {
            $err = $errCodesMap[$errCode];
        }
        else {
            $err = $errCodesMap[self::E_FAILED];
        }

        $mDecomposed = ModelAbstractAdminsOrdersDecomposed::getModel($order['type']);
        $dOrders = $mDecomposed->getActiveOrdersByOrderId($order['id'], Model::LIMIT_1, ['requestGroupStrId']);

        if (Model::inst()->inTransaction()) {
            Model::inst()->rollback();
        }
        Model::inst()->beginTransaction();

        if ($dOrders) {
            $mDecomposed->disableByOrderId($order['id']);
            ModelExchangesRequestsStack::inst()->disableByGroupStrId($dOrders[0]['requestGroupStrId']);
        }
        ModelAdminsOrders::inst()->updateStatus(
            $order['id'], ModelAdminsOrders::STATUS_FAILED, $err['statusCode'], ErrHandler::getFormattedErrMsg($e)
        );

        Model::inst()->commit();

        $this->_errorsCount++;

        if (!$err['fatal']) {
            Verbose::echo1("ERROR: " . $e->getMessage());
        }
        else {
            throw $e;
        }
    }
}