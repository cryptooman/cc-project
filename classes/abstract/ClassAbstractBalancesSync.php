<?php
/**
 *
 */
abstract class ClassAbstractBalancesSync
{
    const SYNC_TYPE_NON_POSITION = 'nonPosition';
    const SYNC_TYPE_POSITION = 'position';

    protected $_apiKey;
    protected $_apiKeysModel;
    protected $_apiKeyEntity;
    protected $_balances;
    protected $_balancesModel;
    protected $_balanceEntity;
    protected $_balancesBuyTypes = [];
    protected $_balancesSellTypes = [];
    protected $_balancesBuyStatus;
    protected $_balancesSellStatus;
    protected $_balancesBuyRequestGroupStrId;
    protected $_balancesSellRequestGroupStrId;
    protected $_exchangeApi;

    protected $_isSyncedBuy = false;
    protected $_isSyncedSell = false;

    function __construct(int $apiKeyId, ModelAbstractApiKeys $mApiKeys, ModelAbstractBalances $mBalances)
    {
        $this->_apiKeysModel = $mApiKeys;
        $this->_apiKeyEntity = $mApiKeys::ENTITY;

        $this->_balancesModel = $mBalances;
        $this->_balanceEntity = $mBalances::ENTITY;

        if ($this->_apiKeyEntity != $this->_balanceEntity) {
            throw new Err("Mismatch entities: api keys [$this->_apiKeyEntity] balances [$this->_balanceEntity]");
        }

        $apiKey = $this->_apiKeysModel->getKeyById($apiKeyId);
        if (!$apiKey) {
            throw new Err("Failed to get api key [$this->_apiKeyEntity : $apiKeyId]");
        }
        $this->_validateApiKey($apiKey);
        $this->_apiKey = $apiKey;

        // NOTE: If api key gets correct data, then previously failed balances must be updated and set as active
        $balances = $this->_balancesModel->getEnabledBalancesByApiKeyId($this->_apiKey['id'], Model::LIMIT_MAX);
        if (!$balances) {
            throw new Err("Failed to get balances [$this->_balanceEntity] for api key [%s]", $this->_apiKey['id']);
        }
        $this->_validateBalances($balances);
        $this->_balances = $balances;

        $this->_balancesBuyTypes = [
            $this->_balancesModel::TYPE_TRADING, $this->_balancesModel::TYPE_DEPOSIT, $this->_balancesModel::TYPE_EXCHANGE
        ];
        $this->_balancesSellTypes = [
            $this->_balancesModel::TYPE_POSITION
        ];

        foreach ($balances as $balance) {
            if (in_array($balance['type'], $this->_balancesBuyTypes)) {
                if (!$this->_balancesBuyStatus) {
                    $this->_balancesBuyStatus = $balance['status'];
                }
                if (!$this->_balancesBuyRequestGroupStrId) {
                    $this->_balancesBuyRequestGroupStrId = $balance['requestGroupStrId'];
                }
            }
            elseif (in_array($balance['type'], $this->_balancesSellTypes)) {
                if (!$this->_balancesSellStatus) {
                    $this->_balancesSellStatus = $balance['status'];
                }
                if (!$this->_balancesSellRequestGroupStrId) {
                    $this->_balancesSellRequestGroupStrId = $balance['requestGroupStrId'];
                }
            }
            else {
                throw new Err("Bad condition");
            }
        }

        $this->_exchangeApi = ClassAbstractExchangeApi::getApi($this->_apiKey['exchangeId']);
    }

    // Balances of type non-position
    function runBuy()
    {
        Verbose::echo1("Syncing balances [$this->_balanceEntity] for api key [%s]", $this->_apiKey['id']);
        Verbose::echo2("Api key: ", ClassAbstractApiKey::formatVerbose($this->_apiKey));
        Verbose::echo2("Balances: ", $this->_balances);

        switch ($this->_balancesBuyStatus) {
            case $this->_balancesModel::STATUS_NEW:
                $this->_doBalancesBuyStatusNew();
                break;
            case $this->_balancesModel::STATUS_SYNCING:
                $this->_doBalancesBuyStatusSyncing();
                break;
            case $this->_balancesModel::STATUS_SYNCED:
                $this->_doBalancesBuyStatusSynced();
                break;
            case $this->_balancesModel::STATUS_FAILED:
                // NOTE: Failed balances can be also processed if api key is live
                //       In case of success response balances will be unset as failed (set as synced)
                $this->_doBalancesBuyStatusFailed();
                break;
            default:
                throw new Err("Bad condition");
        }
    }

    // Balances of type position
    function runSell()
    {
        Verbose::echo1("Syncing balances [$this->_balanceEntity] for api key [%s]", $this->_apiKey['id']);
        Verbose::echo2("Api key: ", ClassAbstractApiKey::formatVerbose($this->_apiKey));
        Verbose::echo2("Balances: ", $this->_balances);

        switch ($this->_balancesSellStatus) {
            case $this->_balancesModel::STATUS_NEW:
                $this->_doBalancesSellStatusNew();
                break;
            case $this->_balancesModel::STATUS_SYNCING:
                $this->_doBalancesSellStatusSyncing();
                break;
            case $this->_balancesModel::STATUS_SYNCED:
                $this->_doBalancesSellStatusSynced();
                break;
            case $this->_balancesModel::STATUS_FAILED:
                // NOTE: Failed balances can be also processed if api key is live
                //       In case of success response balances will be unset as failed (set as synced)
                $this->_doBalancesSellStatusFailed();
                break;
            default:
                throw new Err("Bad condition");
        }
    }

    function isSyncedBuy(): bool
    {
        return $this->_isSyncedBuy;
    }

    function isSyncedSell(): bool
    {
        return $this->_isSyncedSell;
    }

    protected function _doBalancesBuyStatusNew()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        Model::inst()->beginTransaction();
        foreach ($this->_balancesBuyTypes as $type) {
            $this->_balancesModel->updateStatusesByApiKeyIdAndType(
                $this->_apiKey['id'], $type, $this->_balancesModel::STATUS_SYNCING, $this->_balancesModel::STATUS_CODE_SYNCING, ''
            );
        }
        Model::inst()->commit();

        $request = $this->_exchangeApi->buildRequestGetBalances();
        if (!$request) {
            throw new Err(
                "Failed to build get balances [$this->_balanceEntity] request for api key [%s]: ", $this->_apiKey['id']
            );
        }

        $requestToStack = [
            'strId'             => $this->_balancesBuyRequestGroupStrId,
            'groupStrId'        => $this->_balancesBuyRequestGroupStrId,
            'exchangeId'        => $this->_apiKey['exchangeId'],
            'requesterType'     => $this->_balancesModel::REQUESTER_TYPE,
            'requestUrl'        => $request['url'],
            'requestMethod'     => $request['method'],
            'requestHeaders'    => Json::encode($request['headers']),
            'requestData'       => Json::encode($request['data']),
            'requestNonce'      => $request['nonce'],
        ];

        if ($this->_balanceEntity == ModelAbstractBalances::ENTITY_SYSTEM) {
            $requestToStack['systemApiKeyId'] = $this->_apiKey['id'];
            $requestToStack['userApiKeyId'] = 0;
        }
        elseif ($this->_balanceEntity == ModelAbstractBalances::ENTITY_USER) {
            $requestToStack['systemApiKeyId'] = 0;
            $requestToStack['userApiKeyId'] = $this->_apiKey['id'];
        }
        else {
            throw new Err("Bad entity [$this->_balanceEntity]");
        }

        ModelExchangesRequestsStack::pushRequestToBuffer($requestToStack);
        ModelExchangesRequestsStack::flushRequestsBuffer();
    }

    protected function _doBalancesSellStatusNew()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        Model::inst()->beginTransaction();
        foreach ($this->_balancesSellTypes as $type) {
            $this->_balancesModel->updateStatusesByApiKeyIdAndType(
                $this->_apiKey['id'], $type, $this->_balancesModel::STATUS_SYNCING, $this->_balancesModel::STATUS_CODE_SYNCING, ''
            );
        }
        Model::inst()->commit();

        $request = $this->_exchangeApi->buildRequestGetPositions();
        if (!$request) {
            throw new Err(
                "Failed to build get balances [$this->_balanceEntity] positions request for api key [%s]: ", $this->_apiKey['id']
            );
        }

        $requestToStack = [
            'strId'             => $this->_balancesSellRequestGroupStrId,
            'groupStrId'        => $this->_balancesSellRequestGroupStrId,
            'exchangeId'        => $this->_apiKey['exchangeId'],
            'requesterType'     => $this->_balancesModel::REQUESTER_TYPE,
            'requestUrl'        => $request['url'],
            'requestMethod'     => $request['method'],
            'requestHeaders'    => Json::encode($request['headers']),
            'requestData'       => Json::encode($request['data']),
            'requestNonce'      => $request['nonce'],
        ];

        if ($this->_balanceEntity == ModelAbstractBalances::ENTITY_SYSTEM) {
            $requestToStack['systemApiKeyId'] = $this->_apiKey['id'];
            $requestToStack['userApiKeyId'] = 0;
        }
        elseif ($this->_balanceEntity == ModelAbstractBalances::ENTITY_USER) {
            $requestToStack['systemApiKeyId'] = 0;
            $requestToStack['userApiKeyId'] = $this->_apiKey['id'];
        }
        else {
            throw new Err("Bad entity [$this->_balanceEntity]");
        }

        ModelExchangesRequestsStack::pushRequestToBuffer($requestToStack);
        ModelExchangesRequestsStack::flushRequestsBuffer();
    }

    protected function _doBalancesBuyStatusSyncing()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        $request = ModelExchangesRequestsStack::inst()->getAndValidateUnprocessedRequestByStrId($this->_balancesBuyRequestGroupStrId);
        Verbose::echo1("Request stack id: ", $request['id']);
        Verbose::echo2("Request to exchange: ", ModelExchangesRequestsStack::formatVerbose($request));

        if ($request['__inProgress']) {
            Verbose::echo1("Request stack [%s] is in progress with status [%s]", $request['id'], $request['status']);
            return;
        }

        $error = '';
        $balances = $this->_exchangeApi->parseResponseGetBalances($request['status'], $request['responseBody'], $error);
        if (!$balances) {
            $this->_setBalancesAsFailed($this->_balancesBuyTypes, $request['id'], $error, $this->_balancesModel::STATUS_CODE_FAILED);
            return;
        }

        Verbose::echo1("Balances [$this->_balanceEntity] rows for update: ", count($balances));

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($request['id']);

        foreach ($balances as $balance) {
            if (!$balance['currencyId']) {
                Verbose::echo2("Skip empty currencyId: balance [$this->_balanceEntity] for api key [%s]: ", $this->_apiKey['id'], $balance);
                continue;
            }

            Verbose::echo2("Updating balance [$this->_balanceEntity] for api key [%s] with: ", $this->_apiKey['id'], $balance);

            if ($this->_balanceEntity == $this->_balancesModel::ENTITY_SYSTEM) {
                $this->_balancesModel->fundsSync(
                    $this->_apiKey['id'], $balance['currencyId'], $balance['type'], $balance['amount'], $balance['available']
                );
            }
            elseif ($this->_balanceEntity == $this->_balancesModel::ENTITY_USER) {
                $this->_balancesModel->fundsSync(
                    $this->_apiKey['userId'], $this->_apiKey['id'], $balance['currencyId'], $balance['type'], $balance['amount'], $balance['available']
                );
            }
            else {
                throw new Err("Bad condition");
            }
        }

        foreach ($this->_balancesBuyTypes as $type) {
            $this->_balancesModel->updateStatusesByApiKeyIdAndType(
                $this->_apiKey['id'], $type, $this->_balancesModel::STATUS_SYNCED, $this->_balancesModel::STATUS_CODE_SYNCED, ''
            );
        }

        Model::inst()->commit();

        $this->_isSyncedBuy = true;
    }

    protected function _doBalancesSellStatusSyncing()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);

        $request = ModelExchangesRequestsStack::inst()->getAndValidateUnprocessedRequestByStrId($this->_balancesSellRequestGroupStrId);
        Verbose::echo1("Request stack id: ", $request['id']);
        Verbose::echo2("Request to exchange: ", ModelExchangesRequestsStack::formatVerbose($request));

        if ($request['__inProgress']) {
            Verbose::echo1("Request stack [%s] is in progress with status [%s]", $request['id'], $request['status']);
            return;
        }

        $error = '';
        $positions = $this->_exchangeApi->parseResponseGetPositions($request['status'], $request['responseBody'], $error);
        if ($error) {
            $this->_setBalancesAsFailed($this->_balancesSellTypes, $request['id'], $error, $this->_balancesModel::STATUS_CODE_FAILED);
            return;
        }

        $balances = [];

        if ($positions) {
            Verbose::echo1("Positions rows: ", count($positions));
            foreach ($positions as $position) {
                if (!$position['currPairId']) {
                    Verbose::echo2(
                        "Skip: Empty currency pair id balance [$this->_balanceEntity] position for api key [%s]: ", $this->_apiKey['id'], $position
                    );
                    continue;
                }
                $pairId = ModelCurrenciesPairs::inst()->getPairById($position['currPairId'], ['currency1Id']);
                if (!$pairId) {
                    throw new Err("Failed to get currency pair [%s]", $position['currPairId']);
                }
                $balances[] = [
                    'currencyId'    => $pairId['currency1Id'],
                    'type'          => $this->_balancesModel::TYPE_POSITION,
                    'amount'        => $position['amount'],
                    'available'     => $position['amount'],
                ];
            }
        }
        else {
            Verbose::echo1("No positions rows: Setting all balances [%s] to zero", $this->_balancesModel::TYPE_POSITION);
            if ($this->_balanceEntity == $this->_balancesModel::ENTITY_SYSTEM) {
                $posBalances = $this->_balancesModel->getBalancesByApiKeyIdAndType(
                    $this->_apiKey['id'], $this->_balancesModel::TYPE_POSITION, Model::LIMIT_MAX
                );
            }
            elseif ($this->_balanceEntity == $this->_balancesModel::ENTITY_USER) {
                $posBalances = $this->_balancesModel->getBalancesByUserIdApiKeyIdAndType(
                    $this->_apiKey['userId'], $this->_apiKey['id'], $this->_balancesModel::TYPE_POSITION, Model::LIMIT_MAX
                );
            }
            else {
                throw new Err("Bad condition");
            }

            if ($posBalances) {
                foreach ($posBalances as $posBalance) {
                    $balances[] = [
                        'currencyId'    => $posBalance['currencyId'],
                        'type'          => $posBalance['type'],
                        'amount'        => 0,
                        'available'     => 0,
                    ];
                }
            }
        }

        Verbose::echo1("Balances [$this->_balanceEntity] rows for update: ", count($balances));

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($request['id']);

        foreach ($balances as $balance) {
            if (!$balance['currencyId']) {
                Verbose::echo2("Skip: Empty currency id balance [$this->_balanceEntity] for api key [%s]: ", $this->_apiKey['id'], $balance);
                continue;
            }

            Verbose::echo2("Updating balance [$this->_balanceEntity] for api key [%s] with: ", $this->_apiKey['id'], $balance);

            if ($this->_balanceEntity == $this->_balancesModel::ENTITY_SYSTEM) {
                $this->_balancesModel->fundsSync(
                    $this->_apiKey['id'], $balance['currencyId'], $balance['type'], $balance['amount'], $balance['available']
                );
            }
            elseif ($this->_balanceEntity == $this->_balancesModel::ENTITY_USER) {
                $this->_balancesModel->fundsSync(
                    $this->_apiKey['userId'], $this->_apiKey['id'],
                    $balance['currencyId'], $balance['type'], $balance['amount'], $balance['available']
                );
            }
            else {
                throw new Err("Bad condition");
            }
        }

        foreach ($this->_balancesSellTypes as $type) {
            $this->_balancesModel->updateStatusesByApiKeyIdAndType(
                $this->_apiKey['id'], $type, $this->_balancesModel::STATUS_SYNCED, $this->_balancesModel::STATUS_CODE_SYNCED, ''
            );
        }

        Model::inst()->commit();

        $this->_isSyncedSell = true;
    }

    protected function _doBalancesBuyStatusSynced()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_doBalancesBuyStatusNew();
    }

    protected function _doBalancesSellStatusSynced()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_doBalancesSellStatusNew();
    }

    protected function _doBalancesBuyStatusFailed()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_doBalancesBuyStatusNew();
    }

    protected function _doBalancesSellStatusFailed()
    {
        Verbose::echo1("Doing [%s]", __FUNCTION__);
        $this->_doBalancesSellStatusNew();
    }

    protected function _validateApiKey(array $apiKey)
    {
        if (
            !$apiKey['id']
            || !$apiKey['exchangeId']
            || !$apiKey['hash']
            || !$apiKey['public']
            || !$apiKey['secretEncrypted']
            || $apiKey['status'] != $this->_apiKeysModel::STATUS_LIVE
            || $apiKey['statusCode'] != $this->_apiKeysModel::STATUS_CODE_LIVE
            || !$apiKey['enabled']
        ) {
            throw new Err("Bad api key [$this->_apiKeyEntity]: ", ClassAbstractApiKey::formatVerbose($apiKey));
        }
    }

    protected function _validateBalances(array $balances)
    {
        foreach ($balances as $balance) {
            if (
                !$balance
                || !$balance['id']
                || !$balance['apiKeyId']
                || !$balance['currencyId']
                || !in_array($balance['type'], $this->_balancesModel::getTypes())
                || !$balance['requestStrId']
                || !$balance['requestGroupStrId']
                || !in_array($balance['status'], [
                    $this->_balancesModel::STATUS_NEW,
                    $this->_balancesModel::STATUS_SYNCING,
                    $this->_balancesModel::STATUS_SYNCED,
                    $this->_balancesModel::STATUS_FAILED,
                    // Not used now
                    //$this->_balancesModel::STATUS_CODE_SPECIAL,
                ])
                || !isset($balance['statusCode'])
                || !isset($balance['amount'])
                || !isset($balance['amountInUsd'])
                || !isset($balance['available'])
                || !isset($balance['hold'])
                || !$balance['enabled']
            ) {
                throw new Err("Bad balance [$this->_balanceEntity]: ", $balance);
            }
        }
    }

    protected function _setBalancesAsFailed(
        array $balancesTypes, int $requestId, string $error, int $statusCode = ModelAbstractBalances::STATUS_CODE_FAILED
    )
    {
        Verbose::echo1("FAILED: Balances [$this->_balanceEntity] for api key [%s] failed: ", $this->_apiKey['id'], $error);

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($requestId);

        foreach ($balancesTypes as $type) {
            if ($this->_balancesModel->isActiveBalancesExistsByApiKeyIdAndType($this->_apiKey['id'], $type)) {
                $this->_balancesModel->updateStatusesByApiKeyIdAndType(
                    $this->_apiKey['id'], $type, $this->_balancesModel::STATUS_FAILED, $statusCode, $error
                );
            }
        }

        Model::inst()->commit();

        $this->_isSyncedBuy = true;
        $this->_isSyncedSell = true;
    }
}