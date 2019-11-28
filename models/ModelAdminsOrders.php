<?php
/**
 *
 */
class ModelAdminsOrders extends ModelAbstractAdminsOrders
{
    protected $_table = 'adminsOrders';

    static function makeGroupId(): int
    {
        return strftime("%Y%m%d%H%M%S", time()) . explode('.', ClassDateTime::microTime('.', 4))[1];
    }

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UBIGINT, [1] ],
            'adminId'           => [ Vars::UINT, [1] ],
            'groupId'           => [ Vars::UBIGINT, [1] ],
            'type'              => [ Vars::ENUM, self::getTypes() ],
            'priority'          => [ Vars::UINT ],
            'approvedAdminId'   => [ Vars::UINT ],
            'status'            => [ Vars::ENUM, self::getStatuses() ],
            'statusCode'        => [ Vars::ENUM, self::getStatusCodes() ],
            'statusMsg'         => [ Vars::STR, [0], function($v) { return Str::cutAddDots($v, 2048); } ],
            'enabled'           => [ Vars::BOOL ],
            'created'           => [ Vars::DATETIME ],
            'updated'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getLatestOrdersAndData(int $limit): array
    {
        $rows = $this->query(
            "SELECT
                o.id, o.adminId, o.groupId, o.type, o.priority, o.approvedAdminId, o.status, o.statusCode, o.statusMsg,
                
                otn.complexity AS otnComplexity, 
                otn.dataSnapshotId AS otnDataSnapshotId, 
                otn.currPairId AS otnCurrPairId, 
                otn.amountPrice AS otnAmountPrice, 
                otn.amountMultiplier AS otnAmountMultiplier, 
                otn.amount AS otnAmount, 
                otn.remain AS otnRemain, 
                otn.price AS otnPrice, 
                otn.priceAvgExec AS otnPriceAvgExec, 
                otn.side AS otnSide, 
                otn.exec AS otnExec, 
                otn.fee AS otnFee,
                otn.availableInUsdSum AS otnAvailableInUsdSum,
                
                otr.replaceOrderId,
                otr.complexity AS otrComplexity,
                otr.dataSnapshotId AS otrDataSnapshotId,
                otr.currPairId AS otrCurrPairId,
                otr.amountPrice AS otrAmountPrice, 
                otr.amountMultiplier AS otrAmountMultiplier,
                otr.amount AS otrAmount, 
                otr.remain AS otrRemain, 
                otr.price AS otrPrice, 
                otr.priceAvgExec AS otrPriceAvgExec,
                otr.side AS otrSide, 
                otr.exec AS otrExec, 
                otr.fee AS otrFee,
                otr.availableInUsdSum AS otrAvailableInUsdSum,                
                                
                otc.cancelOrderId,
                                
                o.enabled, o.created, o.updated
                
            FROM $this->_table AS o
            LEFT JOIN " . ModelAdminsOrdersTypeNew::inst()->table() . " AS otn
            ON o.id = otn.orderId
            LEFT JOIN " . ModelAdminsOrdersTypeReplace::inst()->table() . " AS otr
            ON o.id = otr.orderId
            LEFT JOIN " . ModelAdminsOrdersTypeCancel::inst()->table() . " AS otc
            ON o.id = otc.orderId
            ORDER BY o.id DESC
            LIMIT $limit"
        )->rows();
        if (!$rows) {
            return [];
        }

        $res = [];
        foreach ($rows as $row) {
            $res[] = [
                'id'                => $row['id'],
                'adminId'           => $row['adminId'],
                'groupId'           => $row['groupId'],
                'type'              => $row['type'],
                'priority'          => $row['priority'],
                'approvedAdminId'   => $row['approvedAdminId'],
                'status'            => $row['status'],
                'statusCode'        => $row['statusCode'],
                'statusMsg'         => $row['statusMsg'],

                'complexity'        => isset($row['otnComplexity']) ? $row['otnComplexity'] :
                                        (isset($row['otrComplexity']) ? $row['otrComplexity'] : ''),

                'dataSnapshotId'    => isset($row['otnDataSnapshotId']) ? $row['otnDataSnapshotId'] :
                                        (isset($row['otrDataSnapshotId']) ? $row['otrDataSnapshotId'] : ''),

                'currPairId'        => isset($row['otnCurrPairId']) ? $row['otnCurrPairId'] :
                                        (isset($row['otrCurrPairId']) ? $row['otrCurrPairId'] : ''),

                'amountPrice'       => isset($row['otnAmountPrice']) ? $row['otnAmountPrice'] :
                                        (isset($row['otrAmountPrice']) ? $row['otrAmountPrice'] : ''),

                'amountMultiplier'  => isset($row['otnAmountMultiplier']) ? $row['otnAmountMultiplier'] :
                                        (isset($row['otrAmountMultiplier']) ? $row['otrAmountMultiplier'] : ''),

                'amount'            => isset($row['otnAmount']) ? $row['otnAmount'] :
                                        (isset($row['otrAmount']) ? $row['otrAmount'] : ''),

                'remain'            => isset($row['otnRemain']) ? $row['otnRemain'] :
                                        (isset($row['otrRemain']) ? $row['otrRemain'] : ''),

                'price'             => isset($row['otnPrice']) ? $row['otnPrice'] :
                                        (isset($row['otrPrice']) ? $row['otrPrice'] : ''),

                'priceAvgExec'      => isset($row['otnPriceAvgExec']) ? $row['otnPriceAvgExec'] :
                                        (isset($row['otrPriceAvgExec']) ? $row['otrPriceAvgExec'] : ''),

                'side'              => isset($row['otnSide']) ? $row['otnSide'] :
                                        (isset($row['otrSide']) ? $row['otrSide'] : ''),

                'exec'              => isset($row['otnExec']) ? $row['otnExec'] :
                                        (isset($row['otrExec']) ? $row['otrExec'] : ''),

                'fee'               => isset($row['otnFee']) ? $row['otnFee'] :
                                        (isset($row['otrFee']) ? $row['otrFee'] : ''),

                'availableInUsdSum' => isset($row['otnAvailableInUsdSum']) ? $row['otnAvailableInUsdSum'] :
                                        (isset($row['otrAvailableInUsdSum']) ? $row['otrAvailableInUsdSum'] : ''),

                'replaceOrderId'    => isset($row['replaceOrderId']) ? $row['replaceOrderId'] : '',
                'cancelOrderId'     => isset($row['cancelOrderId']) ? $row['cancelOrderId'] : '',

                'enabled'           => $row['enabled'],
                'created'           => $row['created'],
                'updated'           => $row['updated'],
            ];
        }
        return $res;
    }

    function getOrderById(int $id, array $cols = ['*']): array
    {
        return $this->_getRowById($id, $cols);
    }

    function getOrderAndDataById(int $id): array
    {
        $order = $this->getOrderById($id);
        if (!$order) {
            return [];
        }

        $orderData = $this->_getOrderDataByIdAndType($id, $order['type']);
        if (!$orderData) {
            throw new Err("Failed to get order [$id] data");
        }

        foreach ($orderData as $k => $v) {
            if (isset($order[$k])) {
                throw new Err("Equal order data key [$k]: ", $order, $orderData);
            }
            $order[$k] = $v;
        }
        return $order;
    }

    function getActivePriorityOrdersByStatus(int $status, int $limit, array $cols = ['*']): array
    {
        $this->_isStatusActive($status);
        return $this->query(
            "SELECT %cols%
            FROM $this->_table 
            WHERE status = :status AND enabled = 1
            ORDER BY priority DESC, id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['status' => $status]
        )->rows();
    }

    function getActivePriorityOrdersByStatusCode(int $statusCode, int $limit, array $cols = ['*']): array
    {
        $this->_isStatusCodeActive($statusCode);
        return $this->query(
            "SELECT %cols%
            FROM $this->_table 
            WHERE statusCode = :statusCode AND enabled = 1
            ORDER BY priority DESC, id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['statusCode' => $statusCode]
        )->rows();
    }

    function getOrderExchangesById(int $id): array
    {
        return ModelAdminsOrdersExchanges::inst()->getExchangesByOrderId($id, self::LIMIT_MAX);
    }

    function getOrderActiveExchangesById(int $id): array
    {
        return ModelAdminsOrdersExchanges::inst()->getActiveExchangesByOrderId($id, self::LIMIT_MAX);
    }

    function getOrderStatsById(int $id): array
    {
        $stats = ModelAdminsOrdersStats::inst()->getStatsByOrderId($id);
        if (!$stats) {
            throw new Err("Failed to get order [$id] stats");
        }
        return $stats;
    }

    function isOrderActiveById(int $id): bool
    {
        $order = $this->getOrderById($id);
        if (!$order) {
            return false;
        }
        if (
            !$this->_isStatusActive($order['status'], false)
            || !$this->_isStatusCodeActive($order['statusCode'], false)
            || !$order['enabled']
        ) {
            return false;
        }
        return true;
    }

    function isOrderActiveBySelf(array $order): bool
    {
        if (
            !$this->_isStatusActive($order['status'], false)
            || !$this->_isStatusCodeActive($order['statusCode'], false)
            || !$order['enabled']
        ) {
            return false;
        }
        return true;
    }

    function isOrderCancelActiveById(int $id): bool
    {
        $order = $this->getOrderById($id);
        if (!$order) {
            return false;
        }

        $cancelOrder = ModelAdminsOrdersTypeCancel::inst()->getOrderByCancelOrderId($id);
        if (!$cancelOrder) {
            return false;
        }

        if (!$this->isOrderActiveBySelf($order)) {
            return false;
        }
        return true;
    }

    function insertOrderTypeNew(
        int $adminId,
        int $groupId,
        string $complexity,
        int $dataSnapshotId,
        array $exchangeIds,
        int $currPairId,
        float $amountPrice,
        float $amountMultiplier,
        float $amount,
        float $price,
        string $side,
        string $exec,
        int $priority = self::PRIORITY_TYPE_NEW
    ): int
    {
        $amountPrice = NumFloat::floor($amountPrice);
        $amount = NumFloat::floor($amount);
        $price = NumFloat::floor($price);

        if (!$exchangeIds) {
            throw new Err("Empty exchangeIds");
        }

        if (!($currPair = ModelCurrenciesPairs::inst()->getActivePairById($currPairId))) {
            throw new Err("Curr pair [$currPairId] is inactive");
        }
        if (!ModelCurrencies::inst()->getActiveCurrencyById($currPair['currency1Id'], ['id'])) {
            throw new Err("Curr [%s] is inactive", $currPair['currency1Id']);
        }
        if (!ModelCurrencies::inst()->getActiveCurrencyById($currPair['currency2Id'], ['id'])) {
            throw new Err("Curr [%s] is inactive", $currPair['currency2Id']);
        }

        foreach ($exchangeIds as $exchangeId) {
            if (!ModelExchanges::inst()->getActiveExchangeById($exchangeId, ['id'])) {
                throw new Err("Exchange [$exchangeId] is inactive");
            }
            if (!ModelExchangesCurrenciesPairs::inst()->getActivePairByExchangeIdPairId($exchangeId, $currPairId)) {
                throw new Err("Exchange [$exchangeId] curr pair [$currPairId] is inactive");
            }
        }

        if ($complexity == self::COMPLEXITY_TYPE1) {
            if ($dataSnapshotId || $amountPrice || $amountMultiplier || $amount < self::AMOUNT_MIN) {
                throw new Err("Bad order data: ", func_get_args());
            }
            $this->checkOrderAmount($exchangeIds, $currPairId, $amount);
            $this->checkOrderPrice($exchangeIds, $currPairId, $price);
        }
        elseif ($complexity == self::COMPLEXITY_TYPE2) {
            if ($amountPrice < self::PRICE_MIN || $amount) {
                throw new Err("Bad order data: ", func_get_args());
            }
            $this->checkOrderPrice($exchangeIds, $currPairId, $price);
        }
        else {
            throw new Err("Bad condition");
        }

        $this->beginTransaction();

        $orderId = $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'adminId'   => $adminId,
                'groupId'   => $groupId,
                'type'      => self::TYPE_NEW,
                'priority'  => $priority,
            ])]
        )->exec()->lastId();

        ModelAdminsOrdersTypeNew::inst()->insert(
            $orderId, $complexity, $dataSnapshotId, $currPairId, $amountPrice, $amountMultiplier, $amount, $price, $side, $exec
        );

        foreach ($exchangeIds as $exchangeId) {
            ModelAdminsOrdersExchanges::inst()->insert($orderId, $exchangeId);
        }

        ModelAdminsOrdersStats::inst()->insert($orderId);

        $this->commit();

        return $orderId;
    }

    function insertOrderTypeReplace(
        int $adminId,
        int $replaceOrderId,
        int $groupId,
        string $complexity,
        int $dataSnapshotId,
        array $exchangeIds,
        int $currPairId,
        float $amountPrice,
        float $amountMultiplier,
        float $amount,
        float $price,
        string $side,
        string $exec,
        int $priority = self::PRIORITY_TYPE_REPLACE
    ): int
    {
        $amountPrice = NumFloat::floor($amountPrice);
        $amount = NumFloat::floor($amount);
        $price = NumFloat::floor($price);

        if (!$exchangeIds) {
            throw new Err("Empty exchangeIds");
        }

        if (!($currPair = ModelCurrenciesPairs::inst()->getActivePairById($currPairId))) {
            throw new Err("Curr pair [$currPairId] is inactive");
        }
        if (!ModelCurrencies::inst()->getActiveCurrencyById($currPair['currency1Id'], ['id'])) {
            throw new Err("Curr [%s] is inactive", $currPair['currency1Id']);
        }
        if (!ModelCurrencies::inst()->getActiveCurrencyById($currPair['currency2Id'], ['id'])) {
            throw new Err("Curr [%s] is inactive", $currPair['currency2Id']);
        }

        foreach ($exchangeIds as $exchangeId) {
            if (!ModelExchanges::inst()->getActiveExchangeById($exchangeId, ['id'])) {
                throw new Err("Exchange [$exchangeId] is inactive");
            }
            if (!ModelExchangesCurrenciesPairs::inst()->getActivePairByExchangeIdPairId($exchangeId, $currPairId)) {
                throw new Err("Exchange [$exchangeId] curr pair [$currPairId] is inactive");
            }
        }

        if ($complexity == self::COMPLEXITY_TYPE1) {
            if ($dataSnapshotId || $amountPrice || $amountMultiplier || $amount < self::AMOUNT_MIN) {
                throw new Err("Bad order data: ", func_get_args());
            }
            $this->checkOrderAmount($exchangeIds, $currPairId, $amount);
            $this->checkOrderPrice($exchangeIds, $currPairId, $price);
        }
        elseif ($complexity == self::COMPLEXITY_TYPE2) {
            if ($amountPrice < self::PRICE_MIN || $amount) {
                throw new Err("Bad order data: ", func_get_args());
            }
            $this->checkOrderPrice($exchangeIds, $currPairId, $price);
        }
        else {
            throw new Err("Bad condition");
        }

        $this->checkOrderToReplace($replaceOrderId);

        $this->beginTransaction();

        $orderId = $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'adminId'   => $adminId,
                'groupId'   => $groupId,
                'type'      => self::TYPE_REPLACE,
                'priority'  => $priority,
            ])]
        )->exec()->lastId();

        ModelAdminsOrdersTypeReplace::inst()->insert(
            $orderId, $replaceOrderId, $complexity, $dataSnapshotId, $currPairId, $amountPrice, $amountMultiplier, $amount, $price, $side, $exec
        );

        foreach ($exchangeIds as $exchangeId) {
            ModelAdminsOrdersExchanges::inst()->insert($orderId, $exchangeId);
        }

        ModelAdminsOrdersStats::inst()->insert($orderId);

        $this->commit();

        return $orderId;
    }

    function insertOrderTypeCancel(
        int $adminId,
        int $cancelOrderId,
        int $groupId,
        int $priority = self::PRIORITY_TYPE_CANCEL
    )
    {
        $this->checkOrderToCancel($cancelOrderId);

        $this->beginTransaction();

        $orderId = $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'adminId'   => $adminId,
                'groupId'   => $groupId,
                'type'      => self::TYPE_CANCEL,
                'priority'  => $priority,
            ])]
        )->exec()->lastId();

        ModelAdminsOrdersTypeCancel::inst()->insert($orderId, $cancelOrderId);

        ModelAdminsOrdersStats::inst()->insert($orderId);

        $this->commit();

        return $orderId;
    }

    function approve(int $id, int $adminId)
    {
        $admin = ModelAdmins::inst()->getActiveAdminById($adminId, ['role']);
        if (!$admin || !in_array($admin['role'], [ModelAdmins::ROLE_ADMIN, ModelAdmins::ROLE_SUDO])) {
            throw new Err("Bad approve admin [$adminId] order [$id]");
        }

        $this->beginTransaction();

        $this->query(
            "UPDATE $this->_table 
            SET approvedAdminId = :approvedAdminId
            WHERE %where%
                  AND approvedAdminId = 0 
                  AND enabled = 1",
            $this->filter([
                'approvedAdminId' => $adminId
            ]),
            ['%where%' => [
                'id' => $id,
                'statusCode' => self::STATUS_CODE_DOING_WAIT_APPROVE,
            ]]
        )->exec()->affectedRows(self::AFFECTED_ONE);

        $this->updateStatus($id, self::STATUS_DOING, self::STATUS_CODE_DOING_APPROVED);
        // NOTE: Skipped setting same status for decomposed orders as it is not necessary

        $this->commit();
    }

    function updateStatus(int $id, string $status, int $statusCode, string $statusMsg = '')
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
        )->exec()->affectedRows(self::AFFECTED_ANY);
    }

    function updateStats(int $orderId, array $stats)
    {
        ModelAdminsOrdersStats::inst()->updateStats($orderId, $stats);
    }

    function updateAmount(int $id, string $type, float $amount)
    {
        $amount = NumFloat::floor($amount);
        if ($amount <= 0) {
            throw new Err("Bad amount [$amount]");
        }
        ModelAbstractAdminsOrdersType::getModel($type)->updateAmount($id, $amount);
    }

    function updateAvailableInUsdSum(int $id, string $type, float $availableInUsdSum)
    {
        $availableInUsdSum = NumFloat::floor($availableInUsdSum);
        if ($availableInUsdSum <= 0) {
            throw new Err("Bad availableInUsdSum [$availableInUsdSum]");
        }
        ModelAbstractAdminsOrdersType::getModel($type)->updateAvailableInUsdSum($id, $availableInUsdSum);
    }

    function updateRemain(int $id, string $type, float $remain)
    {
        $remain = NumFloat::floor($remain);
        ModelAbstractAdminsOrdersType::getModel($type)->updateRemain($id, $remain);
    }

    function updatePriceAvgExec(int $id, string $type, float $price)
    {
        $price = NumFloat::floor($price);
        if ($price <= 0) {
            throw new Err("Bad price [$price]");
        }
        ModelAbstractAdminsOrdersType::getModel($type)->updatePriceAvgExec($id, $price);
    }

    function updateFee(int $id, string $type, float $fee)
    {
        $fee = NumFloat::floor($fee);
        ModelAbstractAdminsOrdersType::getModel($type)->updateFee($id, $fee);
    }

    function checkOrderAmount(array $exchangeIds, int $currPairId, float $amount, bool $exception = true)
    {
        $amount = NumFloat::floor($amount);
        foreach ($exchangeIds as $exchangeId) {
            $exCurrPair = ModelExchangesCurrenciesPairs::inst()->getActivePairByExchangeIdPairId($exchangeId, $currPairId);
            if (!$exCurrPair) {
                throw new Exception("Failed to get exchange [$exchangeId] curr pair [$currPairId]");
            }
            if ($amount < $exCurrPair['orderAmountMin'] || $amount > $exCurrPair['orderAmountMax']) {
                if ($exception) {
                    throw new Err("Bad order input amount [$amount] for exchange currency pair: ", $exCurrPair, func_get_args());
                }
                return false;
            }
        }
        return true;
    }

    function checkOrderPrice(array $exchangeIds, int $currPairId, float $price, bool $exception = true)
    {
        $price = NumFloat::floor($price);
        foreach ($exchangeIds as $exchangeId) {
            // TODO: Tmp: Must be not an array
            if (is_array($exchangeId)) {
                throw new Err("An array: ", $exchangeIds, $exchangeId, func_get_args());
            }

            $exCurrPair = ModelExchangesCurrenciesPairs::inst()->getActivePairByExchangeIdPairId($exchangeId, $currPairId);
            if (!$exCurrPair) {
                throw new Exception("Failed to get exchange [$exchangeId] curr pair [$currPairId]");
            }
            if ($price < $exCurrPair['orderPriceMin'] || $price > $exCurrPair['orderPriceMax']) {
                if ($exception) {
                    throw new Err("Bad order input price [$price] for exchange currency pair: ", $exCurrPair, func_get_args());
                }
                return false;
            }
        }
        return true;
    }

    function checkOrderToReplace(int $id)
    {
        $order = $this->getOrderById($id);
        if (!$order) {
            throw new Err("Order [$id] to replace not found");
        }
        if (!in_array($order['type'], [ModelAdminsOrders::TYPE_NEW, ModelAdminsOrders::TYPE_REPLACE])) {
            throw new Err("Bad order [$id] type [%s] to replace", $order['type']);
        }
        if ($order['status'] != ModelAdminsOrders::STATUS_DOING) {
            throw new Err("Bad order [$id] status [%s] to replace", $order['status']);
        }
        if (
            !in_array($order['statusCode'], [
                // Now not allowed
                //ModelAdminsOrders::STATUS_CODE_DOING_WAIT_APPROVE,
                //ModelAdminsOrders::STATUS_CODE_DOING_APPROVED,
                //ModelAdminsOrders::STATUS_CODE_DOING_CREATE_WAIT_REQ,
                ModelAdminsOrders::STATUS_CODE_DOING_CREATED,
                ModelAdminsOrders::STATUS_CODE_DOING_STATE_WAIT_REQ,
            ])
        ) {
            throw new Err("Bad order [$id] status code [%s] to replace", $order['statusCode']);
        }
    }

    function checkOrderToCancel(int $id)
    {
        $order = $this->getOrderById($id);
        if (!$order) {
            throw new Err("Order [$id] to cancel not found");
        }
        if (!in_array($order['type'], [ModelAdminsOrders::TYPE_NEW, ModelAdminsOrders::TYPE_REPLACE])) {
            throw new Err("Bad order [$id] type [%s] to cancel", $order['type']);
        }
        if (!in_array($order['status'], [ModelAdminsOrders::STATUS_NEW, ModelAdminsOrders::STATUS_DOING])) {
            throw new Err("Bad order [$id] status [%s] to cancel", $order['status']);
        }
        if (
            !in_array($order['statusCode'], [
                ModelAdminsOrders::STATUS_CODE_NEW,
                ModelAdminsOrders::STATUS_CODE_DOING_WAIT_APPROVE,
                ModelAdminsOrders::STATUS_CODE_DOING_APPROVED,
                ModelAdminsOrders::STATUS_CODE_DOING_CREATE_WAIT_REQ,
                ModelAdminsOrders::STATUS_CODE_DOING_CREATED,
                ModelAdminsOrders::STATUS_CODE_DOING_STATE_WAIT_REQ,
            ])
        ) {
            throw new Err("Bad order [$id] status code [%s] to cancel", $order['statusCode']);
        }
    }

    private function _getOrderDataByIdAndType(int $id, string $type)
    {
        if ($type == self::TYPE_NEW) {
            $data = ModelAdminsOrdersTypeNew::inst()->getOrderByOrderId($id, [
                'complexity',
                'dataSnapshotId',
                'currPairId',
                'amountPrice',
                'amountMultiplier',
                'amount',
                'remain',
                'price',
                'priceAvgExec',
                'side',
                'exec',
                'fee',
                'availableInUsdSum',
            ]);
        }
        elseif ($type == self::TYPE_REPLACE) {
            $data = ModelAdminsOrdersTypeReplace::inst()->getOrderByOrderId($id, [
                'replaceOrderId',
                'complexity',
                'dataSnapshotId',
                'currPairId',
                'amountPrice',
                'amountMultiplier',
                'amount',
                'remain',
                'price',
                'priceAvgExec',
                'side',
                'exec',
                'fee',
                'availableInUsdSum',
            ]);
        }
        elseif ($type == self::TYPE_CANCEL) {
            $data = ModelAdminsOrdersTypeCancel::inst()->getOrderByOrderId($id, [
                'cancelOrderId'
            ]);
        }
        else {
            throw new Err("Bad order [$id] type [$type] to get data");
        }

        if (!$data) {
            throw new Err("Failed to get order [$id] data");
        }
        return $data;
    }
}