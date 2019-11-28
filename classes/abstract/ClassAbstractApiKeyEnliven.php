<?php
/**
 * Enliven api keys (set live status if possible)
 */
abstract class ClassAbstractApiKeyEnliven
{
    protected $_apiKey;
    protected $_apiKeysModel;
    protected $_apiKeyEntity;
    protected $_exchangeApi;

    function __construct(int $apiKeyId, ModelAbstractApiKeys $mApiKeys)
    {
        $this->_apiKeysModel = $mApiKeys;
        $this->_apiKeyEntity = $mApiKeys::ENTITY;

        $apiKey = $this->_apiKeysModel->getKeyById($apiKeyId);
        if (!$apiKey) {
            throw new Err("Failed to get api key [$this->_apiKeyEntity:$apiKeyId]");
        }
        $this->_validateApiKey($apiKey);
        $this->_apiKey = $apiKey;

        $this->_exchangeApi = ClassAbstractExchangeApi::getApi($this->_apiKey['exchangeId']);
    }

    function run()
    {
        Verbose::echo1("Enliven api key [$this->_apiKeyEntity:%s]", $this->_apiKey['id']);
        Verbose::echo2("Api key: ", ClassAbstractApiKey::formatVerbose($this->_apiKey));

        switch ($this->_apiKey['status']) {
            case $this->_apiKeysModel::STATUS_NEW:
                $this->_doApiKeyStatusNew();
                break;
            case $this->_apiKeysModel::STATUS_RENEW:
                $this->_doApiKeyStatusRenew();
                break;
            case $this->_apiKeysModel::STATUS_CHECKING:
                $this->_doApiKeyStatusChecking();
                break;
            default:
                throw new Err("Bad condition");
        }
    }

    protected function _doApiKeyStatusNew()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        $request = $this->_exchangeApi->buildRequestGetApiKeyPermissions();
        if (!$request) {
            throw new Err(
                "Failed to build get permissions request for api key [$this->_apiKeyEntity:%s]: ", $this->_apiKey['id']
            );
        }

        $requestToStack = [
            'strId'             => $this->_apiKey['requestStrId'],
            'groupStrId'        => $this->_apiKey['requestStrId'],
            'exchangeId'        => $this->_apiKey['exchangeId'],
            'requesterType'     => $this->_apiKeysModel::REQUESTER_TYPE,
            'isApiKeyEnliven'   => true,
            'requestUrl'        => $request['url'],
            'requestMethod'     => $request['method'],
            'requestHeaders'    => Json::encode($request['headers']),
            'requestData'       => Json::encode($request['data']),
            'requestNonce'      => $request['nonce'],
        ];
        if ($this->_apiKeyEntity == ModelAbstractApiKeys::ENTITY_SYSTEM) {
            $requestToStack['systemApiKeyId'] = $this->_apiKey['id'];
            $requestToStack['userApiKeyId'] = 0;
        }
        elseif ($this->_apiKeyEntity == ModelAbstractApiKeys::ENTITY_USER) {
            $requestToStack['systemApiKeyId'] = 0;
            $requestToStack['userApiKeyId'] = $this->_apiKey['id'];
        }
        else {
            throw new Err("Bad condition");
        }

        ModelExchangesRequestsStack::pushRequestToBuffer($requestToStack);

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::flushRequestsBuffer();

        $this->_apiKeysModel->updateStatus(
            $this->_apiKey['id'], $this->_apiKeysModel::STATUS_CHECKING, $this->_apiKeysModel::STATUS_CODE_CHECKING
        );

        Model::inst()->commit();
    }

    protected function _doApiKeyStatusRenew()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_doApiKeyStatusNew();
    }

    protected function _doApiKeyStatusChecking()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        $request = ModelExchangesRequestsStack::inst()->getAndValidateUnprocessedRequestByStrId($this->_apiKey['requestStrId']);
        Verbose::echo1("Request stack id: ", $request['id']);
        Verbose::echo2("Request to exchange: ", ModelExchangesRequestsStack::formatVerbose($request));

        if ($request['__inProgress']) {
            Verbose::echo1("Request stack [%s] is in progress with status [%s]", $request['id'], $request['status']);
            return;
        }

        $error = '';
        $isPermValid = $this->_exchangeApi->parseResponseGetApiKeyPermissions($request['status'], $request['responseBody'], $error);
        if (!$isPermValid) {
            $this->_setApiKeyAsFailed(
                $request['id'], $error, $this->_apiKeysModel::STATUS_CODE_FAILED_BAD_PERMISSIONS
            );
            return;
        }

        Verbose::echo1("Api key [$this->_apiKeyEntity:%s] permissions are valid", $this->_apiKey['id']);

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($request['id']);

        $this->_apiKeysModel->updateStatus(
            $this->_apiKey['id'], $this->_apiKeysModel::STATUS_LIVE, $this->_apiKeysModel::STATUS_CODE_LIVE
        );

        Model::inst()->commit();
    }

    protected function _validateApiKey(array $apiKey)
    {
        if (
            !$apiKey['id']
            || !$apiKey['exchangeId']
            || !$apiKey['hash']
            || !$apiKey['public']
            || !$apiKey['secretEncrypted']
            || !in_array($apiKey['status'], [
                $this->_apiKeysModel::STATUS_NEW, $this->_apiKeysModel::STATUS_RENEW, $this->_apiKeysModel::STATUS_CHECKING
            ])
            || !isset($apiKey['statusCode'])
            || !$apiKey['enabled']
        ) {
            throw new Err("Bad api key [$this->_apiKeyEntity]: ", ClassAbstractApiKey::formatVerbose($apiKey));
        }
    }

    protected function _setApiKeyAsFailed(int $requestId, string $error, int $statusCode = ModelAbstractApiKeys::STATUS_CODE_FAILED)
    {
        Verbose::echo1("FAILED: Api key [$this->_apiKeyEntity:%s] failed: ", $this->_apiKey['id'], $error);

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($requestId);

        $this->_apiKeysModel->updateStatus(
            $this->_apiKey['id'], $this->_apiKeysModel::STATUS_FAILED, $statusCode, $error
        );

        Model::inst()->commit();
    }
}