<?php
/**
 *
 */
class ClassAdminDirectRequest
{
    private $_directRequest = [];
    private $_apiKey;
    private $_apiKeysModel;
    private $_apiKeyEntity;
    private $_apiKeyId;
    private $_exchangeApi;

    function __construct(int $directRequestId)
    {
        $dRequest = ModelAdminsDirectExchangesRequests::inst()->getActiveRequestById($directRequestId);
        if (!$dRequest) {
            throw new Err("Failed to get direct request [$directRequestId]");
        }
        $this->_validateDirectRequest($dRequest);
        $this->_directRequest = $dRequest;

        if ($this->_directRequest['systemApiKeyId']) {
            $this->_apiKeysModel = ModelSystemApiKeys::inst();
            $this->_apiKeyEntity = $this->_apiKeysModel::ENTITY;
            $this->_apiKeyId = $this->_directRequest['systemApiKeyId'];
        }
        elseif ($this->_directRequest['userApiKeyId']) {
            $this->_apiKeysModel = ModelUsersApiKeys::inst();
            $this->_apiKeyEntity = $this->_apiKeysModel::ENTITY;
            $this->_apiKeyId = $this->_directRequest['userApiKeyId'];
        }
        else {
            throw new Err("Bad condition");
        }

        $apiKey = $this->_apiKeysModel->getKeyById($this->_apiKeyId);
        if (!$apiKey) {
            throw new Err("Failed to get api key [$this->_apiKeyEntity:$this->_apiKeyId]");
        }
        $this->_validateApiKey($apiKey, $this->_directRequest['exchangeId']);
        $this->_apiKey = $apiKey;

        $this->_exchangeApi = ClassAbstractExchangeApi::getApi($this->_apiKey['exchangeId']);
    }

    function run()
    {
        Verbose::echo1("Doing admin direct request [%s] for api key [$this->_apiKeyEntity:%s]", $this->_directRequest['id'], $this->_apiKey['id']);
        Verbose::echo2("Direct request: ", $this->_directRequest);

        switch ($this->_directRequest['status']) {
            case ModelAdminsDirectExchangesRequests::STATUS_PENDING:
                $doMethods = [
                    ModelAdminsDirectExchangesRequests::TYPE_GET_ORDERS     => '_doGetOrdersStatusPending',
                    ModelAdminsDirectExchangesRequests::TYPE_GET_ORDER      => '_doGetOrderStatusPending',
                    ModelAdminsDirectExchangesRequests::TYPE_GET_POSITIONS  => '_doGetPositionsStatusPending',
                    ModelAdminsDirectExchangesRequests::TYPE_GET_BALANCES   => '_doGetBalancesStatusPending',
                    ModelAdminsDirectExchangesRequests::TYPE_ORDER_NEW      => '_doOrderNewStatusPending',
                    ModelAdminsDirectExchangesRequests::TYPE_ORDER_REPLACE  => '_doOrderReplaceStatusPending',
                    ModelAdminsDirectExchangesRequests::TYPE_ORDER_CANCEL   => '_doOrderCancelStatusPending',
                    ModelAdminsDirectExchangesRequests::TYPE_API_REQUEST    => '_doApiRequestStatusPending',
                ];
                if (!isset($doMethods[$this->_directRequest['type']])) {
                    throw new Err("Bad method [%s]", $this->_directRequest['type']);
                }
                $method = $doMethods[$this->_directRequest['type']];
                $this->$method();
                break;

            case ModelAdminsDirectExchangesRequests::STATUS_REQUESTING:
                $doMethods = [
                    ModelAdminsDirectExchangesRequests::TYPE_GET_ORDERS     => '_doGetOrdersStatusRequesting',
                    ModelAdminsDirectExchangesRequests::TYPE_GET_ORDER      => '_doGetOrderStatusRequesting',
                    ModelAdminsDirectExchangesRequests::TYPE_GET_POSITIONS  => '_doGetPositionsStatusRequesting',
                    ModelAdminsDirectExchangesRequests::TYPE_GET_BALANCES   => '_doGetBalancesStatusRequesting',
                    ModelAdminsDirectExchangesRequests::TYPE_ORDER_NEW      => '_doOrderNewStatusRequesting',
                    ModelAdminsDirectExchangesRequests::TYPE_ORDER_REPLACE  => '_doOrderReplaceStatusRequesting',
                    ModelAdminsDirectExchangesRequests::TYPE_ORDER_CANCEL   => '_doOrderCancelStatusRequesting',
                    ModelAdminsDirectExchangesRequests::TYPE_API_REQUEST    => '_doApiRequestStatusRequesting',
                ];
                if (!isset($doMethods[$this->_directRequest['type']])) {
                    throw new Err("Bad method [%s]", $this->_directRequest['type']);
                }
                $method = $doMethods[$this->_directRequest['type']];
                $this->$method();
                break;

            default:
                throw new Err("Bad condition");
        }
    }

    private function _doGetOrdersStatusPending()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_pushRequestToStack($this->_exchangeApi->buildRequestGetOrders());
    }

    private function _doGetOrdersStatusRequesting()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_processRequestStackResponse('parseResponseGetOrders');
    }

    private function _doGetOrderStatusPending()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        if (empty($this->_directRequest['requestData']) || !($data = Json::decode($this->_directRequest['requestData'])) ) {
            throw new Err("Bad direct request data: ", $this->_directRequest);
        }
        $this->_pushRequestToStack($this->_exchangeApi->buildRequestGetOrder($data['orderId']));
    }

    private function _doGetOrderStatusRequesting()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_processRequestStackResponse('parseResponseGetOrder');
    }

    private function _doGetPositionsStatusPending()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_pushRequestToStack($this->_exchangeApi->buildRequestGetPositions());
    }

    private function _doGetPositionsStatusRequesting()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_processRequestStackResponse('parseResponseGetPositions');
    }

    private function _doGetBalancesStatusPending()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_pushRequestToStack($this->_exchangeApi->buildRequestGetBalances());
    }

    private function _doGetBalancesStatusRequesting()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_processRequestStackResponse('parseResponseGetBalances');
    }

    private function _doOrderNewStatusPending()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        if (empty($this->_directRequest['requestData']) || !($data = Json::decode($this->_directRequest['requestData'])) ) {
            throw new Err("Bad direct request data: ", $this->_directRequest);
        }
        $this->_pushRequestToStack($this->_exchangeApi->buildRequestCreateOrderTypeNew(
            $data['currPairId'], $data['amount'], $data['price'], $data['side'], $data['exec']
        ));
    }

    private function _doOrderNewStatusRequesting()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_processRequestStackResponse('parseResponseCreatedOrderTypeNew');
    }

    private function _doOrderReplaceStatusPending()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        if (empty($this->_directRequest['requestData']) || !($data = Json::decode($this->_directRequest['requestData'])) ) {
            throw new Err("Bad direct request data: ", $this->_directRequest);
        }
        $this->_pushRequestToStack($this->_exchangeApi->buildRequestCreateOrderTypeReplace(
            $data['replaceOrderId'], $data['currPairId'], $data['amount'], $data['price'], $data['side'], $data['exec']
        ));
    }

    private function _doOrderReplaceStatusRequesting()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_processRequestStackResponse('parseResponseCreatedOrderTypeReplace');
    }

    private function _doOrderCancelStatusPending()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        if (empty($this->_directRequest['requestData']) || !($data = Json::decode($this->_directRequest['requestData'])) ) {
            throw new Err("Bad direct request data: ", $this->_directRequest);
        }
        $this->_pushRequestToStack($this->_exchangeApi->buildRequestCreateOrderTypeCancel($data['cancelOrderId']));
    }

    private function _doOrderCancelStatusRequesting()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_processRequestStackResponse('parseResponseCreatedOrderTypeCancel');
    }

    private function _doApiRequestStatusPending()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        if (
            empty($this->_directRequest['requestData'])
            || !($data = Json::decode($this->_directRequest['requestData']))
            || empty($data['__urlAction'])
        ) {
            throw new Err("Bad direct request data: ", $this->_directRequest);
        }

        $urlAction = $data['__urlAction'];
        unset($data['__urlAction']);

        $this->_pushRequestToStack($this->_exchangeApi->buildRequestApiRequest($urlAction, $data));
    }

    private function _doApiRequestStatusRequesting()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_processRequestStackResponse('parseResponseApiRequest');
    }

    private function _validateDirectRequest(array $dRequest)
    {
        if (
            !$dRequest['id']
            || !$dRequest['adminId']
            || !ModelAbstractApiKeys::checkIds($dRequest['systemApiKeyId'], $dRequest['userApiKeyId'], false)
            || !$dRequest['exchangeId']
            || !$dRequest['requestStrId']
            || !in_array($dRequest['type'], ModelAdminsDirectExchangesRequests::getTypes())
            || !in_array($dRequest['status'], [
                ModelAdminsDirectExchangesRequests::STATUS_PENDING, ModelAdminsDirectExchangesRequests::STATUS_REQUESTING
            ])
            || !in_array($dRequest['statusCode'], [
                ModelAdminsDirectExchangesRequests::STATUS_CODE_PENDING, ModelAdminsDirectExchangesRequests::STATUS_CODE_REQUESTING
            ])
            || !$dRequest['enabled']
        ) {
            throw new Err("Bad direct request: ", $dRequest);
        }
    }

    private function _validateApiKey(array $apiKey, int $dRequestExchangeId)
    {
        if (
            !$apiKey['id']
            || !$apiKey['exchangeId']
            || $apiKey['exchangeId'] != $dRequestExchangeId
            || !$apiKey['hash']
            || !$apiKey['public']
            || !$apiKey['secretEncrypted']
            || !in_array($apiKey['status'], [
                ModelAbstractApiKeys::STATUS_NEW,
                ModelAbstractApiKeys::STATUS_RENEW,
                ModelAbstractApiKeys::STATUS_CHECKING,
                ModelAbstractApiKeys::STATUS_LIVE,
                ModelAbstractApiKeys::STATUS_FAILED,
                ModelAbstractApiKeys::STATUS_SPECIAL,
            ])
            || !$apiKey['enabled']
        ) {
            throw new Err("Bad api key [$this->_apiKeyEntity]: ", ClassAbstractApiKey::formatVerbose($apiKey), $dRequestExchangeId);
        }
    }

    private function _pushRequestToStack(array $requestBuild)
    {
        if (!$requestBuild) {
            throw new Err(
                "Empty request build for api key [$this->_apiKeyEntity:%s]: ", $this->_apiKey['id']
            );
        }

        $requestToStack = [
            'strId'             => $this->_directRequest['requestStrId'],
            'groupStrId'        => $this->_directRequest['requestStrId'],
            'exchangeId'        => $this->_directRequest['exchangeId'],
            'requesterType'     => ModelAdminsDirectExchangesRequests::REQUESTER_TYPE,
            'isDirectRequest'   => true,
            'requestUrl'        => $requestBuild['url'],
            'requestMethod'     => $requestBuild['method'],
            'requestHeaders'    => Json::encode($requestBuild['headers']),
            'requestData'       => Json::encode($requestBuild['data']),
            'requestNonce'      => $requestBuild['nonce'],
        ];

        if ($this->_apiKeyEntity == ModelAbstractApiKeys::ENTITY_SYSTEM) {
            if (!empty($this->_apiKey['user'])) {
                throw new Err("Bad api key: ", ClassAbstractApiKey::formatVerbose($this->_apiKey));
            }
            $requestToStack['systemApiKeyId'] = $this->_apiKey['id'];
            $requestToStack['userApiKeyId'] = 0;
        }
        elseif ($this->_apiKeyEntity == ModelAbstractApiKeys::ENTITY_USER) {
            if (empty($this->_apiKey['user'])) {
                throw new Err("Bad api key: ", ClassAbstractApiKey::formatVerbose($this->_apiKey));
            }
            $requestToStack['systemApiKeyId'] = 0;
            $requestToStack['userApiKeyId'] = $this->_apiKey['id'];
        }
        else {
            throw new Err("Bad condition");
        }

        ModelAdminsDirectExchangesRequests::inst()->setRequesting($this->_directRequest['id'], $requestToStack);
    }

    private function _processRequestStackResponse(string $parseResponseApiMethod)
    {
        $request = ModelExchangesRequestsStack::inst()->getAndValidateUnprocessedRequestByStrId($this->_directRequest['requestStrId']);
        Verbose::echo1("Request stack id: ", $request['id']);
        Verbose::echo2("Request to exchange: ", ModelExchangesRequestsStack::formatVerbose($request));

        if ($request['__inProgress']) {
            Verbose::echo1("Request stack [%s] is in progress with status [%s]", $request['id'], $request['status']);
            return;
        }

        $error = '';
        $response = $this->_exchangeApi->$parseResponseApiMethod($request['status'], $request['responseBody'], $error);
        if ($error) {
            Verbose::echo1("FAILED: Response from api is failed: $error");
            ModelAdminsDirectExchangesRequests::inst()->setFailedResponse($this->_directRequest['id'], $error, $request['id']);
            return;
        }

        Verbose::echo1("Response from api is success");
        Verbose::echo2("Response from api: ", $response);
        ModelAdminsDirectExchangesRequests::inst()->setSuccessResponse($this->_directRequest['id'], Json::encode($response), $request['id']);
    }
}