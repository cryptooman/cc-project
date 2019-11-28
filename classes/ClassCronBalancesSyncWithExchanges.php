<?php
/**
 *
 */
class ClassCronBalancesSyncWithExchanges extends ClassAbstractCron
{
    const BALANCES_SYNC_LOCK_SEC = 30;

    const E_FAILED              = 4000000;
    const E_UNALIVE_API_KEY     = 4000001;
    const E_DUPLICATE_API_KEY   = 4000002;
    const E_INACTIVE_USER       = 4000003;

    private static $_balancesSyncLock = [];
    private $_liveApiKeysSeen = [];

    function run()
    {
        Verbose::echo1("Syncing balances with exchanges");

        $this->_liveApiKeysSeen = [];
        $this->_syncBalancesForLiveApiKeys(ModelSystemApiKeys::inst(), ModelSystemBalances::inst(), 'ClassSystemBalancesSync');
        $this->_syncBalancesForLiveApiKeys(ModelUsersApiKeys::inst(), ModelUsersBalances::inst(), 'ClassUserBalancesSync');

        Verbose::echo1("Syncing total balances");
        ModelTotalBalances::inst()->syncBalances();

        Verbose::echo1("Errors count: $this->_errorsCount");
    }

    private function _syncBalancesForLiveApiKeys(ModelAbstractApiKeys $mApiKeys, ModelAbstractBalances $mBalances, string $classSyncBalances)
    {
        $apiKeysEntity = $mApiKeys::ENTITY;
        $balancesEntity = $mBalances::ENTITY;
        if ($apiKeysEntity != $balancesEntity) {
            throw new Err("Mismatch entities: api keys [$apiKeysEntity] balances [$balancesEntity]");
        }

        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo1("Doing balances [$balancesEntity] for live api keys");

        // Getting all api keys (live and unlive), because if api key state was changed from live to unlive, then all related balances
        //      need to be set as "failed"
        $apiKeys = $mApiKeys->getKeys(Model::LIMIT_MAX, ['id', 'hash', 'status', 'enabled']);
        $apiKeysTotal = count($apiKeys);

        if ($apiKeys) {
            Verbose::echo1("Api keys [$apiKeysEntity] to process: $apiKeysTotal");

            foreach ($apiKeys as $apiKey) {
                try {
                    Verbose::echo2(Verbose::EMPTY_LINE);
                    Verbose::echo1("Using api key [$apiKeysEntity:%s]", $apiKey['id']);

                    $syncLockId = $apiKeysEntity . '-' . $apiKey['id'];
                    if (
                        empty(self::$_balancesSyncLock[$syncLockId]['time'])
                        || self::$_balancesSyncLock[$syncLockId]['time'] <= time()
                    ) {
                        if (!$mApiKeys->isLiveBySelf($apiKey)) {
                            throw new Err("Unalive api key [$apiKeysEntity:%s]", $apiKey['id'], (new ErrCode(self::E_UNALIVE_API_KEY)));
                        }
                        if (isset($this->_liveApiKeysSeen[$apiKey['hash']])) {
                            throw new Err(
                                "Balances already synced for api key [$apiKeysEntity:%s]", $apiKey['id'], (new ErrCode(self::E_DUPLICATE_API_KEY))
                            );
                        }

                        $syncBalances = new $classSyncBalances($apiKey['id']);

                        if (empty(self::$_balancesSyncLock[$syncLockId][ClassAbstractBalancesSync::SYNC_TYPE_NON_POSITION])) {
                            $syncBalances->runBuy();
                            self::$_balancesSyncLock[$syncLockId][ClassAbstractBalancesSync::SYNC_TYPE_NON_POSITION] = $syncBalances->isSyncedBuy();
                        }
                        if (empty(self::$_balancesSyncLock[$syncLockId][ClassAbstractBalancesSync::SYNC_TYPE_POSITION])) {
                            $syncBalances->runSell();
                            self::$_balancesSyncLock[$syncLockId][ClassAbstractBalancesSync::SYNC_TYPE_POSITION] = $syncBalances->isSyncedSell();
                        }

                        if (
                            !empty(self::$_balancesSyncLock[$syncLockId][ClassAbstractBalancesSync::SYNC_TYPE_NON_POSITION])
                            && !empty(self::$_balancesSyncLock[$syncLockId][ClassAbstractBalancesSync::SYNC_TYPE_POSITION])
                        ) {
                            self::$_balancesSyncLock[$syncLockId]['time'] = time() + self::BALANCES_SYNC_LOCK_SEC;
                            self::$_balancesSyncLock[$syncLockId][ClassAbstractBalancesSync::SYNC_TYPE_NON_POSITION] = false;
                            self::$_balancesSyncLock[$syncLockId][ClassAbstractBalancesSync::SYNC_TYPE_POSITION] = false;

                            $this->_liveApiKeysSeen[$apiKey['hash']] = true;
                        }
                    }
                    else {
                        Verbose::echo1(
                            "Skip: Sync is locked till [%s]", ClassDateTime::dbDateTime(self::$_balancesSyncLock[$syncLockId]['time'])
                        );
                    }
                }
                catch (Exception $e) {
                    $this->_handleError($e, $mBalances, $apiKey['id']);
                    self::$_balancesSyncLock[$syncLockId]['time'] = time() + self::BALANCES_SYNC_LOCK_SEC;
                }
            }
        }
        else {
            Verbose::echo1("No live api keys [$apiKeysEntity]");
        }

        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo1("Balances [$balancesEntity] processed: api keys used [$apiKeysTotal]");
    }

    private function _handleError(Exception $e, ModelAbstractBalances $mBalances, int $apiKeyId)
    {
        $errCodesMap = [
            self::E_FAILED => [
                'statusCode' => ModelAbstractBalances::STATUS_CODE_FAILED,
                'fatal' => true,
            ],
            self::E_UNALIVE_API_KEY => [
                'statusCode' => ModelAbstractBalances::STATUS_CODE_FAILED_UNALIVE_API_KEY,
                'fatal' => false,
            ],
            self::E_DUPLICATE_API_KEY => [
                'statusCode' => ModelAbstractBalances::STATUS_CODE_FAILED_DUPLICATE_API_KEY,
                'fatal' => false,
            ],
            self::E_INACTIVE_USER => [
                'statusCode' => ModelAbstractBalances::STATUS_CODE_FAILED_INACTIVE_USER,
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

        $balances = $mBalances->getBalancesByApiKeyId($apiKeyId, Model::LIMIT_1, ['requestGroupStrId']);
        if (!$balances) {
            throw new Err("Failed to get balances [%s] for api key [$apiKeyId]", $mBalances::ENTITY);
        }
        $requestGroupStrId = $balances[0]['requestGroupStrId'];

        if (Model::inst()->inTransaction()) {
            Model::inst()->rollback();
        }
        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->disableByGroupStrId($requestGroupStrId);

        if ($mBalances->isActiveBalancesExistsByApiKeyId($apiKeyId)) {
            $mBalances->updateStatusesByApiKeyId(
                $apiKeyId, $mBalances::STATUS_FAILED, $err['statusCode'], ErrHandler::getFormattedErrMsg($e)
            );
        }

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