<?php
/**
 * Bitfinex API docs: https://docs.bitfinex.com/v1/reference
 */
class ClassExchangeApiBitfinex extends ClassAbstractExchangeApi
{
    const BASE_URL = 'https://api.bitfinex.com';

    function __construct()
    {
        parent::__construct();
    }

    function doPublicRequest(string $url, string $method = Curl::HTTP_METHOD_GET, array $headers = [], array $data = []): array
    {
        return $this->_doRequest($url, $method, $headers, $data);
    }

    function doAuthedRequest(string $apiKey, string $apiSecret, string $url, string $method, array $headers, array $data): array
    {
        if (empty($data['request']) || empty($data['nonce'])) {
            throw new Err("Bad authed request data: ", $data);
        }

        $authHeaders = $this->_makeAuthedRequestHeaders($data, $apiKey, $apiSecret);
        if (!$authHeaders) {
            throw new Err("Failed to make authed request headers: ", $data);
        }
        unset($apiSecret);

        $headers = array_merge($headers, $authHeaders);

        return $this->_doRequest($url, $method, $headers, $data);
    }

    protected function _doRequest(string $url, string $method, array $headers, array $data): array
    {
        // NOTE: Loop for future use
        $try = 0;
        while ($try++ < 1) {
            $response = parent::_doRequest($url, $method, $headers, $data);
            if ($response['code'] >= 200 && $response['code'] <= 299) {
                $success = true;
            }
            else {
                $success = false;
            }
            //sleep(pow(2, $try));
        }

        return ['success' => $success, 'response' => $response];
    }

    function getCurrPairRatio(int $currPairId, string &$error): array
    {
        $url = self::BASE_URL . '/v1/pubticker/' . strtolower(ModelCurrenciesPairs::inst()->idToCode($currPairId));
        try {
            $response = $this->doPublicRequest($url);
            if (!$response['success']) {
                throw new Err("Response is failed: ", $response);
            }

            $result = $this->_parseResponseBody($response['response']['body']);
            if (
                empty($result['last_price'])
                || (float) $result['last_price'] <= 0
                || empty($result['timestamp'])
                || (int) $result['timestamp'] <= 0
            ) {
                throw new Err("Bad response: ", $response);
            }

            return ['ratio' => NumFloat::floor($result['last_price']), 'exchangeTs' => (int) $result['timestamp']];
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return [];
        }
    }

    function buildRequestGetApiKeyPermissions(): array
    {
        // https://docs.bitfinex.com/v1/reference#auth-key-permissions

        $nonce = $this->_makeNonce();
        $request = [
            'url'       => self::BASE_URL . '/v1/key_info',
            'method'    => ModelExchangesRequestsStack::REQUEST_METHOD_POST,
            'headers'   => [],
            'data' => [
                'request'   => '/v1/key_info',
                'nonce'     => $nonce,
                'exchange'  => 'bitfinex',
            ],
            'nonce' => $nonce,
        ];
        return $request;
    }

    function buildRequestGetOrders(): array
    {
        // https://docs.bitfinex.com/v1/reference#rest-auth-active-orders

        $nonce = $this->_makeNonce();
        $request = [
            'url'       => self::BASE_URL . '/v1/orders',
            'method'    => ModelExchangesRequestsStack::REQUEST_METHOD_POST,
            'headers'   => [],
            'data' => [
                'request'   => '/v1/orders',
                'nonce'     => $nonce,
                'exchange'  => 'bitfinex',
            ],
            'nonce' => $nonce,
        ];
        return $request;
    }

    function buildRequestGetOrder(int $exOrderId): array
    {
        // https://docs.bitfinex.com/v1/reference#rest-auth-order-status

        if (!$exOrderId) {
            throw new Err("Empty exchange order id [$exOrderId]");
        }
        $nonce = $this->_makeNonce();
        $request = [
            'url'       => self::BASE_URL . '/v1/order/status',
            'method'    => ModelExchangesRequestsStack::REQUEST_METHOD_POST,
            'headers'   => [],
            'data' => [
                'request'   => '/v1/order/status',
                'nonce'     => $nonce,
                'exchange'  => 'bitfinex',
                'order_id'  => $exOrderId,
            ],
            'nonce' => $nonce,
        ];
        return $request;
    }

    function buildRequestGetPositions(): array
    {
        // https://docs.bitfinex.com/v1/reference#rest-auth-active-positions

        $nonce = $this->_makeNonce();
        $request = [
            'url'       => self::BASE_URL . '/v1/positions',
            'method'    => ModelExchangesRequestsStack::REQUEST_METHOD_POST,
            'headers'   => [],
            'data' => [
                'request'   => '/v1/positions',
                'nonce'     => $nonce,
                'exchange'  => 'bitfinex',
            ],
            'nonce' => $nonce,
        ];
        return $request;
    }

    function buildRequestGetBalances(): array
    {
        // https://docs.bitfinex.com/v1/reference#rest-auth-wallet-balances

        $nonce = $this->_makeNonce();
        $request = [
            'url'       => self::BASE_URL . '/v1/balances',
            'method'    => ModelExchangesRequestsStack::REQUEST_METHOD_POST,
            'headers'   => [],
            'data' => [
                'request'   => '/v1/balances',
                'nonce'     => $nonce,
                'exchange'  => 'bitfinex',
            ],
            'nonce' => $nonce,
        ];
        return $request;
    }

    function buildRequestCreateOrderTypeNew(int $currPairId, float $amount, float $price, string $side, string $exec): array
    {
        // https://docs.bitfinex.com/v1/reference#rest-auth-new-order

        if (!$currPairId || !$amount || !$price || !$side || !$exec) {
            throw new Err("Bad data to build request: ", func_get_args());
        }
        $nonce = $this->_makeNonce();
        $request = [
            'url'       => self::BASE_URL . '/v1/order/new',
            'method'    => ModelExchangesRequestsStack::REQUEST_METHOD_POST,
            'headers'   => [],
            'data' => [
                'request'       => '/v1/order/new',
                'nonce'         => $nonce,
                'exchange'      => 'bitfinex',
                'symbol'        => strtolower(ModelCurrenciesPairs::inst()->idToCode($currPairId)),
                'amount'        => (string) $amount,
                'price'         => (string) $price,
                'side'          => $side,
                'type'          => $exec,
                'is_hidden'     => false,
                'is_postonly'   => false,
                'ocoorder'      => false,
            ],
            'nonce' => $nonce,
        ];
        return $request;
    }

    function buildRequestCreateOrderTypeReplace(
        int $exReplaceOrderId, int $currPairId, float $amount, float $price, string $side, string $exec
    ): array
    {
        // https://docs.bitfinex.com/v1/reference#rest-auth-replace-order

        if (!$exReplaceOrderId || !$currPairId || !$amount || !$price || !$side || !$exec) {
            throw new Err("Bad data to build request: ", func_get_args());
        }
        $nonce = $this->_makeNonce();
        $request = [
            'url'       => self::BASE_URL . '/v1/order/cancel/replace',
            'method'    => ModelExchangesRequestsStack::REQUEST_METHOD_POST,
            'headers'   => [],
            'data' => [
                'request'       => '/v1/order/cancel/replace',
                'nonce'         => $nonce,
                'exchange'      => 'bitfinex',
                'order_id'      => $exReplaceOrderId,
                'symbol'        => strtolower(ModelCurrenciesPairs::inst()->idToCode($currPairId)),
                'amount'        => (string) $amount,
                'price'         => (string) $price,
                'side'          => $side,
                'type'          => $exec,
                'is_hidden'     => false,
                'is_postonly'   => false,
                'use_remaining' => false,
            ],
            'nonce' => $nonce,
        ];
        return $request;
    }

    function buildRequestCreateOrderTypeCancel(int $exCancelOrderId): array
    {
        // https://docs.bitfinex.com/v1/reference#rest-auth-cancel-order

        if (!$exCancelOrderId) {
            throw new Err("Bad data to build request: ", func_get_args());
        }
        $nonce = $this->_makeNonce();
        $request = [
            'url'       => self::BASE_URL . '/v1/order/cancel',
            'method'    => ModelExchangesRequestsStack::REQUEST_METHOD_POST,
            'headers'   => [],
            'data' => [
                'request'       => '/v1/order/cancel',
                'nonce'         => $nonce,
                'exchange'      => 'bitfinex',
                'order_id'      => $exCancelOrderId,
            ],
            'nonce' => $nonce,
        ];
        return $request;
    }

    function buildRequestApiRequest(string $urlAction, array $data): array
    {
        $nonce = $this->_makeNonce();
        $request = [
            'url'       => self::BASE_URL . '/v1/' . $urlAction,
            'method'    => ModelExchangesRequestsStack::REQUEST_METHOD_POST,
            'headers'   => [],
            'data'      => array_merge([
                'request'   => '/v1/' . $urlAction,
                'nonce'     => $nonce,
                'exchange'  => 'bitfinex',
            ], $data),
            'nonce'     => $nonce,
        ];
        return $request;
    }

    function parseResponseGetApiKeyPermissions(string $status, string $body, string &$error): bool
    {
        try {
            if ($status == ModelExchangesRequestsStack::STATUS_SUCCESS) {
                $response = $this->_parseResponseBody($body);
                if (
                    // Changes via API must be denied
                    empty($response['account']['read'])
                    || !empty($response['account']['write'])

                    // Changes via API must be denied
                    || empty($response['history']['read'])
                    || !empty($response['history']['write'])

                    || empty($response['orders']['read'])
                    || empty($response['orders']['write'])

                    || empty($response['positions']['read'])
                    || empty($response['positions']['write'])

                    || empty($response['funding']['read'])
                    || empty($response['funding']['write'])

                    || empty($response['wallets']['read'])
                    || empty($response['wallets']['write'])

                    // Changes via API must be denied
                    // Only write permission is available
                    //|| empty($response['withdraw']['read'])
                    || !empty($response['withdraw']['write'])
                ) {
                    throw new Err("Bad api key permissions response: ", $response);
                }
                return true;
            }
            elseif ($status == ModelExchangesRequestsStack::STATUS_FAILED) {
                throw new Err("Failed [%s]: ", __FUNCTION__, $body);
            }
            else {
                throw new Err("Bad status [$status]");
            }
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return false;
        }
    }

    function parseResponseGetOrders(string $status, string $body, string &$error): array
    {
        try {
            if ($status == ModelExchangesRequestsStack::STATUS_SUCCESS) {
                $response = $this->_parseResponseBody($body);
                $orders = [];
                if ($response) {
                    foreach ($response as $resOrder) {
                        $orders[] = $this->_formatResponseOrder($resOrder);
                    }
                }
                return $orders;
            }
            elseif ($status == ModelExchangesRequestsStack::STATUS_FAILED) {
                throw new Err("Failed [%s]: ", __FUNCTION__, $body);
            }
            else {
                throw new Err("Bad status [$status]");
            }
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return [];
        }
    }

    function parseResponseGetOrder(string $status, string $body, string &$error, array $decomposedOrder = []): array
    {
        try {
            if ($status == ModelExchangesRequestsStack::STATUS_SUCCESS) {
                $response = $this->_parseResponseBody($body);
                return $this->_formatResponseOrder($response);
            }
            elseif ($status == ModelExchangesRequestsStack::STATUS_FAILED) {
                throw new Err("Failed [%s]: ", __FUNCTION__, $body);
            }
            else {
                throw new Err("Bad status [$status]");
            }
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return [];
        }
    }

    function parseResponseGetPositions(string $status, string $body, string &$error): array
    {
        try {
            if ($status == ModelExchangesRequestsStack::STATUS_SUCCESS) {
                $response = $this->_parseResponseBody($body);
                $positions = [];
                if ($response) {
                    foreach ($response as $resPosition) {
                        $this->_checkResponsePosition($resPosition);
                        $currPairId = ModelCurrenciesPairs::inst()->codeToId($resPosition['symbol'], false);
                        $positions[] = [
                            'id'            => $resPosition['id'],
                            'currPairId'    => $currPairId,
                            'status'        => $resPosition['status'],
                            'base'          => NumFloat::floor($resPosition['base']),
                            'amount'        => NumFloat::floor($resPosition['amount']),
                            'timestamp'     => $resPosition['timestamp'],
                            'swap'          => NumFloat::floor($resPosition['swap']),
                            'pl'            => NumFloat::floor($resPosition['pl']),
                            'extra'         => (!$currPairId) ? $resPosition['symbol'] : '',
                        ];
                    }
                }
                return $positions;
            }
            elseif ($status == ModelExchangesRequestsStack::STATUS_FAILED) {
                throw new Err("Failed [%s]: ", __FUNCTION__, $body);
            }
            else {
                throw new Err("Bad status [$status]");
            }
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return [];
        }
    }

    function parseResponseGetBalances(string $status, string $body, string &$error): array
    {
        try {
            if ($status == ModelExchangesRequestsStack::STATUS_SUCCESS) {
                $response = $this->_parseResponseBody($body);
                $balances = [];
                if ($response) {
                    foreach ($response as $resBalance) {
                        $this->_checkResponseBalance($resBalance);
                        $currencyId = ModelCurrencies::inst()->codeToId($resBalance['currency'], false);
                        $balances[] = [
                            'currencyId'    => $currencyId,
                            'type'          => $resBalance['type'],
                            'amount'        => NumFloat::floor($resBalance['amount']),
                            'available'     => NumFloat::floor($resBalance['available']),
                            'extra'         => (!$currencyId) ? $resBalance['currency'] : '',
                        ];
                    }
                }
                return $balances;
            }
            elseif ($status == ModelExchangesRequestsStack::STATUS_FAILED) {
                throw new Err("Failed [%s]: ", __FUNCTION__, $body);
            }
            else {
                throw new Err("Bad status [$status]");
            }
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return [];
        }
    }

    function parseResponseCreatedOrderTypeNew(string $status, string $body, string &$error, array $decomposedOrder = []): int
    {
        try {
            if ($status == ModelExchangesRequestsStack::STATUS_SUCCESS) {
                $response = $this->_parseResponseBody($body);
                $this->_checkResponseOrderNew($response, $decomposedOrder);
                return (int) $response['order_id'];
            }
            elseif ($status == ModelExchangesRequestsStack::STATUS_FAILED) {
                throw new Err("Failed [%s]: ", __FUNCTION__, $body);
            }
            else {
                throw new Err("Bad status [$status]");
            }
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return 0;
        }
    }

    function parseResponseCreatedOrderTypeReplace(string $status, string $body, string &$error, array $decomposedOrder = []): int
    {
        try {
            if ($status == ModelExchangesRequestsStack::STATUS_SUCCESS) {
                $response = $this->_parseResponseBody($body);
                $this->_checkResponseOrderNew($response, $decomposedOrder);

                // Disabled: No replaced order id in response
                //           Need to do a separate request to get replaced order state
                //if ($decomposedOrder && $decomposedOrder['replaceOrderDecomposedId'] != $response['id']) {
                //    throw new Err("Mismatch replaced order ids: ", $response, $decomposedOrder);
                //}

                return (int) $response['order_id'];
            }
            elseif ($status == ModelExchangesRequestsStack::STATUS_FAILED) {
                throw new Err("Failed [%s]: ", __FUNCTION__, $body);
            }
            else {
                throw new Err("Bad status [$status]");
            }
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return 0;
        }
    }

    function parseResponseCreatedOrderTypeCancel(string $status, string $body, string &$error, array $decomposedOrder = []): bool
    {
        try {
            if ($status == ModelExchangesRequestsStack::STATUS_SUCCESS) {
                $response = $this->_parseResponseBody($body);
                $this->_checkResponseOrder($response, $decomposedOrder);

                // Disabled: Cancelled order status can be set with a delay
                //           Need to do a separate request to get cancelled order state
                //$order = $this->_formatResponseOrder($response, $decomposedOrder);
                //if (
                //    $order['status'] != ModelAbstractAdminsOrdersDecomposed::STATUS_REJECTED
                //    || $order['statusCode'] != ModelAbstractAdminsOrdersDecomposed::STATUS_CODE_REJECTED_CANCELLED
                //) {
                //    throw new Err("Bad status of cancelled order: ", $order, $response);
                //}
                //return [
                //    'id' => $order['id'],
                //    'amount' => $order['amount'],
                //    'remain' => $order['remain'],
                //];

                return true;
            }
            elseif ($status == ModelExchangesRequestsStack::STATUS_FAILED) {
                throw new Err("Failed [%s]: ", __FUNCTION__, $body);
            }
            else {
                throw new Err("Bad status [$status]");
            }
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return false;
        }
    }

    function parseResponseApiRequest(string $status, string $body, string &$error): array
    {
        try {
            if ($status == ModelExchangesRequestsStack::STATUS_SUCCESS) {
                return $this->_parseResponseBody($body);
            }
            elseif ($status == ModelExchangesRequestsStack::STATUS_FAILED) {
                throw new Err("Failed [%s]: ", __FUNCTION__, $body);
            }
            else {
                throw new Err("Bad status [$status]");
            }
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            return [];
        }
    }

    private function _makeAuthedRequestHeaders(array $data, string $apiKey, string $apiSecret): array
    {
        $dataB64 = Base64::encode(Json::encode($data));
        return [
            'X-BFX-APIKEY' => $apiKey,
            'X-BFX-PAYLOAD' => $dataB64,
            'X-BFX-SIGNATURE' => HashHmac::sha384($dataB64, $apiSecret)
        ];
    }

    private function _makeNonce(): string
    {
        $n = ClassDateTime::microTime('');
        if (!$n) {
            throw new Err("Failed to make nonce");
        }
        return $n;
    }

    private function _parseResponseBody(string $body): array
    {
        if (!$body) {
            throw new Err("Response body is empty");
        }
        $response = Json::decode($body);
        Verbose::echo2("Response from exchange: ", $response);
        return $response;
    }

    private function _checkResponseOrderNew(array $response, array $decomposedOrder = [])
    {
        $this->_checkResponseOrder($response, $decomposedOrder);
        if(
            empty($response['order_id'])
            || ((int) $response['order_id']) <= 0
            || (!empty($decomposedOrder['exchangeOrderId']) && $response['order_id'] != $decomposedOrder['exchangeOrderId'])
            || $response['order_id'] != $response['id']
        ) {
            throw new Err("Bad order response [%s:1]: ", __FUNCTION__, $response, $decomposedOrder);
        }

        if (
            $decomposedOrder
            && (!empty($decomposedOrder['exchangeOrderId']) && $response['order_id'] != $decomposedOrder['exchangeOrderId'])
        ) {
            throw new Err("Bad order response [%s:2]: ", __FUNCTION__, $response, $decomposedOrder);
        }
    }

    private function _checkResponseOrder(array $response, array $decomposedOrder = [])
    {
        if (
            !$response
            || !isset($response['id'])
            || ((int) $response['id']) <= 0
            // Not necessary to check
            //|| empty($response['exchange'])
            //|| $response['exchange'] != 'bitfinex'
            || empty($response['symbol'])
            || !isset($response['original_amount'])
            || (float) $response['original_amount'] <= 0
            || !isset($response['remaining_amount'])
            || (float) $response['remaining_amount'] < 0
            || (float) $response['remaining_amount'] > (float) $response['original_amount']
            || !isset($response['price'])
            || (float) $response['price'] <= 0
            || (float) $response['avg_execution_price'] < 0
            || empty($response['side'])
            || !in_array($response['side'], ModelAbstractAdminsOrders::getSides())
            || empty($response['type'])
            || !in_array($response['type'], ModelAbstractAdminsOrders::getExecs())
            || !isset($response['timestamp'])
            || $response['timestamp'] <= 0
            || !isset($response['is_live'])
            || !isset($response['is_cancelled'])
            || ($response['is_live'] && $response['is_cancelled'])
            || (
                // Completed order
                !$response['is_live'] && !$response['is_cancelled']
                && (float) $response['remaining_amount'] != 0 && (float) $response['original_amount'] != (float) $response['executed_amount']
            )
            || !empty($response['is_hidden'])
            || !empty($response['oco_order'])
            || !empty($response['was_forced'])
        ) {
            throw new Err("Bad order response [%s:1]: ", __FUNCTION__, $response);
        }

        if ($decomposedOrder) {
            if (
                (!empty($decomposedOrder['exchangeOrderId']) && $response['id'] != $decomposedOrder['exchangeOrderId'])
                || (
                    !empty($decomposedOrder['currPairId'])
                    && $response['symbol'] != strtolower(ModelCurrenciesPairs::inst()->idToCode($decomposedOrder['currPairId']))
                )
                || (!empty($decomposedOrder['amount']) && (float) $response['original_amount'] != (float) $decomposedOrder['amount'])
                || (
                    !empty($decomposedOrder['price'])
                    && $decomposedOrder['exec'] != ModelAbstractAdminsOrdersDecomposed::EXEC_MARKET
                    && (float) $response['price'] != (float) $decomposedOrder['price']
                )
                || (!empty($decomposedOrder['side']) && $response['side'] != $decomposedOrder['side'])
                || (!empty($decomposedOrder['exec']) && $response['type'] != $decomposedOrder['exec'])
            ) {
                throw new Err("Bad order response [%s:2]: ", __FUNCTION__, $response, $decomposedOrder);
            }
        }
    }

    private function _responseOrderToStatusAndCode(array $response): array
    {
        if ($response['is_cancelled']) {
            $status = ModelAbstractAdminsOrdersDecomposed::STATUS_REJECTED;
            $statusCode = ModelAbstractAdminsOrdersDecomposed::STATUS_CODE_REJECTED_CANCELLED;
        }
        elseif ($response['is_live']) {
            $status = ModelAbstractAdminsOrdersDecomposed::STATUS_DOING;
            $statusCode = ModelAbstractAdminsOrdersDecomposed::STATUS_CODE_DOING_CREATED;
        }
        elseif (!$response['is_live']) {
            $status = ModelAbstractAdminsOrdersDecomposed::STATUS_COMPLETED;
            $statusCode = ModelAbstractAdminsOrdersDecomposed::STATUS_CODE_COMPLETED;
        }
        else {
            throw new Err("Bad status response: ", $response);
        }
        return [$status, $statusCode];
    }

    private function _formatResponseOrder(array $resOrder, array $decomposedOrder = []): array
    {
        $this->_checkResponseOrder($resOrder, $decomposedOrder);

        $currPairId = ModelCurrenciesPairs::inst()->codeToId($resOrder['symbol'], false);
        list($orderStatus, $orderStatusCode) = $this->_responseOrderToStatusAndCode($resOrder);
        return [
            'id'            => $resOrder['id'],
            'currPairId'    => $currPairId,
            'amount'        => NumFloat::floor($resOrder['original_amount']),
            'remain'        => NumFloat::floor($resOrder['remaining_amount']),
            'price'         => NumFloat::floor($resOrder['price']),
            'priceAvgExec'  => NumFloat::floor($resOrder['avg_execution_price']),
            'side'          => $resOrder['side'],
            'exec'          => $resOrder['type'],
            'fee'           => NumFloat::floor(0), // There is no fee data in response
            'status'        => $orderStatus,
            'statusCode'    => $orderStatusCode,
            'extra'         => (!$currPairId) ? $resOrder['symbol'] : '',
        ];
    }

    private function _checkResponsePosition(array $response)
    {
        if (
            !$response
            || !isset($response['id'])
            || ((int) $response['id']) <= 0
            || empty($response['symbol'])
            || empty($response['status'])
            || $response['status'] != 'ACTIVE'
            || empty($response['base'])
            || empty($response['amount'])
            // Can be negative
            //|| $response['amount'] < 0
            || empty($response['timestamp'])
            || $response['timestamp'] < 0
            || !isset($response['swap'])
            || !isset($response['pl'])
        ) {
            throw new Err("Bad position response: ", $response);
        }
    }

    private function _checkResponseBalance(array $balance)
    {
        if (
            !$balance
            || empty($balance['type'])
            || !in_array($balance['type'], ModelAbstractBalances::getTypes())
            || empty($balance['currency'])
            || !isset($balance['amount'])
            || !isset($balance['available'])
            || ((float) $balance['available'] && (float) $balance['available'] > (float) $balance['amount'])
        ) {
            throw new Err("Bad balance response: ", $balance);
        }
    }
}