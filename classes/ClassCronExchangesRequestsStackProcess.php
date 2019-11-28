<?php
/**
 *
 */
class ClassCronExchangesRequestsStackProcess extends ClassAbstractCron
{
    const REQUESTS_BULK_SIZE            = 10;
    const SAME_API_KEY_REQUEST_LOCK_SEC = 1;

    // Request will be set as failed (hanged) if exceeds this value
    const REQUEST_IN_STATUS_REQUESTING_SEC_MAX = 600;

    const E_FAILED = 1000000;
    const E_UNALIVE_API_KEY = 1000001;

    private static $_apiKeysRequestLock = [];

    function run(): bool
    {
        Verbose::echo1("Processing exchanges requests stack");

        if (!mt_rand(0, 9)) {
            $this->_setHangedRequestsAsFailed();
        }

        $delayBetweenRequestsUsec = Config::get('admin.exchangesRequests.delayBetweenRequestsUsec');

        $requestsTotal = 0;
        $requestsPreparedTotal = 0;

        $requests = ModelExchangesRequestsStack::inst()->getWaitingRequests(self::REQUESTS_BULK_SIZE, ['*']);
        if ($requests) {
            $requestsTotal = count($requests);
            Verbose::echo1("Requests total: $requestsTotal");

            $requests = $this->_prepareRequests($requests);
            $requestsPreparedTotal = count($requests);
            Verbose::echo1("Requests to process: $requestsPreparedTotal");

            foreach ($requests as $request) {
                try {
                    Verbose::echo2(Verbose::EMPTY_LINE);
                    Verbose::echo1("Doing request [%s]", $request['id']);

                    $reqLockId = $request['systemApiKeyId'] . '-' . $request['userApiKeyId'];
                    if (!isset(self::$_apiKeysRequestLock[$reqLockId]) || self::$_apiKeysRequestLock[$reqLockId] <= time()) {
                        $this->_doRequest($request);
                        self::$_apiKeysRequestLock[$reqLockId] = time() + self::SAME_API_KEY_REQUEST_LOCK_SEC;
                    }
                    else {
                        Verbose::echo1("Skip: Request is locked till [%s]", ClassDateTime::dbDateTime(self::$_apiKeysRequestLock[$reqLockId]));
                    }
                    usleep($delayBetweenRequestsUsec);
                }
                catch (Exception $e) {
                    $this->_handleError($e, $request);
                    self::$_apiKeysRequestLock[$reqLockId] = time() + self::SAME_API_KEY_REQUEST_LOCK_SEC;
                }
            }
        }
        else {
            Verbose::echo1("No waiting requests found");
        }

        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo1("Requests total: $requestsTotal");
        Verbose::echo1("Requests processed: $requestsPreparedTotal");
        Verbose::echo1("Errors count: $this->_errorsCount");

        return (empty($requests)) ? false : true;
    }

    private function _prepareRequests(array $requests): array
    {
        $res = [];
        // Get requests with unique api keys
        $seen = [];
        foreach ($requests as $request) {
            $seenId = $request['systemApiKeyId'] . '-' . $request['userApiKeyId'];
            if (!isset($seen[$seenId])) {
                $res[] = $request;
            }
            $seen[$seenId] = true;
        }
        return $res;
    }

    private function _doRequest(array $request)
    {
        if (
            !$request['id']
            || !$request['strId']
            || !$request['exchangeId']
            || !ModelAbstractApiKeys::checkIds($request['systemApiKeyId'], $request['userApiKeyId'], false)
            || $request['status'] != ModelExchangesRequestsStack::STATUS_WAITING
            || $request['statusCode'] != ModelExchangesRequestsStack::STATUS_CODE_WAITING
            || !$request['requestUrl']
            || !$request['requestMethod']
            || $request['responseCode']
            || !$request['enabled']
        ) {
            throw new Err("Bad request: ", $request);
        }

        ModelExchangesRequestsStack::inst()->updateStatus(
            $request['id'], ModelExchangesRequestsStack::STATUS_REQUESTING, ModelExchangesRequestsStack::STATUS_CODE_REQUESTING
        );

        list($mApiKeys, $apiKeyId, $apiKey, $apiSecret) = $this->_getApiKeyData(
            $request['systemApiKeyId'], $request['userApiKeyId'], $request['isApiKeyEnliven'], $request['isDirectRequest']
        );

        $response = (new ClassExchangeApiBitfinex)->doAuthedRequest(
            $apiKey['public'],
            $apiSecret,
            $request['requestUrl'],
            $request['requestMethod'],
            Json::decode($request['requestHeaders']),
            Json::decode($request['requestData'])
        );
        unset($apiSecret);

        if (!$response) {
            throw new Err("Response is empty for request: ", $request);
        }
        Verbose::echo2("===== Response start =====");
        Verbose::echo2(print_r($response, 1));
        Verbose::echo2("===== Response end =====");

        $apiKey['requestsTotal'] += 1;
        $apiKey['requestLastAt'] = ClassDateTime::dbDateTime(time());

        Model::inst()->beginTransaction();

        if (!empty($response['response']['interfaceIp'])) {
            ModelExchangesRequestsStack::inst()->setRequestInterfaceIp($request['id'], $response['response']['interfaceIp']);
        }

        ModelExchangesRequestsStack::inst()->setResponse(
            $request['id'],
            $response['response']['code'],
            Json::encode($response['response']['headers']),
            $response['response']['body']
        );

        if ($response['success']) {
            Verbose::echo1("Response is success");
            ModelExchangesRequestsStack::inst()->updateStatus(
                $request['id'], ModelExchangesRequestsStack::STATUS_SUCCESS, ModelExchangesRequestsStack::STATUS_CODE_SUCCESS
            );
            $apiKey['requestsFailedSeq'] = 0;
        }
        else {
            Verbose::echo1("FAILED: Response is failed");
            ModelExchangesRequestsStack::inst()->updateStatus(
                $request['id'], ModelExchangesRequestsStack::STATUS_FAILED, ModelExchangesRequestsStack::STATUS_CODE_FAILED
            );
            $apiKey['requestsFailed'] += 1;
            $apiKey['requestsFailedSeq'] += 1;
        }

        $mApiKeys->updateRequestsStats(
            $apiKeyId, $apiKey['requestsTotal'], $apiKey['requestsFailed'], $apiKey['requestsFailedSeq'], $apiKey['requestLastAt']
        );

        Model::inst()->commit();
    }

    private function _getApiKeyData(int $systemApiKeyId, int $userApiKeyId, bool $isApiKeyEnliven, bool $isDirectRequest): array
    {
        $mApiKeys = ModelAbstractApiKeys::getModelByIds($systemApiKeyId, $userApiKeyId);
        $apiKeyId = ModelAbstractApiKeys::getKeyId($systemApiKeyId, $userApiKeyId);

        if (!$isApiKeyEnliven && !$isDirectRequest) {
            $apiKey = $mApiKeys->getLiveKeyById($apiKeyId);
            if (!$apiKey) {
                throw new Err("No live api key [%s:$apiKeyId]", $mApiKeys::ENTITY, (new ErrCode(self::E_UNALIVE_API_KEY)));
            }
        }
        elseif ($isApiKeyEnliven) {
            $apiKey = $mApiKeys->getCheckingKeyById($apiKeyId);
            if (!$apiKey) {
                throw new Err("Failed to get api key [%s:$apiKeyId]", $mApiKeys::ENTITY);
            }
        }
        elseif ($isDirectRequest) {
            $apiKey = $mApiKeys->getKeyForDirectRequestsById($apiKeyId);
            if (!$apiKey) {
                throw new Err("Failed to get api key [%s:$apiKeyId]", $mApiKeys::ENTITY);
            }
        }
        else {
            throw new Err("Bad condition");
        }

        if ($systemApiKeyId) {
            // Extra check id is correct
            if ($apiKeyId !== $systemApiKeyId) {
                throw new Err("Bad api key [%s] id [$apiKeyId != $systemApiKeyId]", $mApiKeys::ENTITY);
            }
            $apiSecret = ClassSystemApiKey::decryptApiSecret($apiKey['secretEncrypted']);
        }
        elseif ($userApiKeyId) {
            // Extra check id is correct
            if ($apiKeyId !== $userApiKeyId) {
                throw new Err("Bad api key [%s] id [$apiKeyId != $userApiKeyId]", $mApiKeys::ENTITY);
            }
            $apiSecret = ClassUserApiKey::decryptApiSecret($apiKey['secretEncrypted'], $apiKey['userId']);
        }

        return [$mApiKeys, $apiKeyId, $apiKey, $apiSecret];
    }

    private function _setHangedRequestsAsFailed()
    {
        $requests = ModelExchangesRequestsStack::inst()->getRequestingRequests(Model::LIMIT_MAX);
        if (!$requests) {
            Verbose::echo1("No hanged requests found");
            return;
        }

        $hanged = [];
        foreach ($requests as $request) {
            if (strtotime($request['updated']) + self::REQUEST_IN_STATUS_REQUESTING_SEC_MAX < time()) {
                Verbose::echo1("Request [%s] is hanged: Set as failed", $request['id']);
                ModelExchangesRequestsStack::inst()->updateStatus(
                    $request['id'],
                    ModelExchangesRequestsStack::STATUS_FAILED,
                    ModelExchangesRequestsStack::STATUS_CODE_FAILED_HANGED_REQUEST,
                    'Request is hanged'
                );
                $hanged[] = $request['id'];
            }
        }
        ErrHandler::log("Hanged requests found [" . count($requests) . "]: " . print_r($hanged, 1));
    }

    private function _handleError(Exception $e, array $request)
    {
        $errCodesMap = [
            self::E_FAILED => [
                'statusCode' => ModelExchangesRequestsStack::STATUS_CODE_FAILED,
                'fatal' => true,
            ],
            self::E_UNALIVE_API_KEY => [
                'statusCode' => ModelExchangesRequestsStack::STATUS_CODE_FAILED_UNALIVE_API_KEY,
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

        $mApiKeys = ModelAbstractApiKeys::getModelByIds($request['systemApiKeyId'], $request['userApiKeyId']);
        $apiKeyId = ModelAbstractApiKeys::getKeyId($request['systemApiKeyId'], $request['userApiKeyId']);

        $apiKey = $mApiKeys->getKeyById($apiKeyId);
        if (!$apiKey) {
            throw new Err("Failed to get api key [%s:$apiKeyId]", $mApiKeys::ENTITY);
        }

        $apiKey['requestsTotal'] += 1;
        $apiKey['requestsFailed'] += 1;
        $apiKey['requestsFailedSeq'] += 1;
        $apiKey['requestLastAt'] = ClassDateTime::dbDateTime(time());

        if (Model::inst()->inTransaction()) {
            Model::inst()->rollback();
        }
        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->updateStatus(
            $request['id'], ModelExchangesRequestsStack::STATUS_FAILED, $err['statusCode'], ErrHandler::getFormattedErrMsg($e)
        );
        $mApiKeys->updateRequestsStats(
            $apiKeyId, $apiKey['requestsTotal'], $apiKey['requestsFailed'], $apiKey['requestsFailedSeq'], $apiKey['requestLastAt']
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