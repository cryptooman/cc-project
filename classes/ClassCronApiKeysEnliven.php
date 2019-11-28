<?php
/**
 *
 */
class ClassCronApiKeysEnliven extends ClassAbstractCron
{
    const E_FAILED = 3000000;
    const E_INACTIVE_USER = 3000001;

    function run()
    {
        Verbose::echo1("Enliven api keys");

        $this->_enlivenApiKeys(ModelSystemApiKeys::inst(), 'ClassSystemApiKeyEnliven');
        $this->_enlivenApiKeys(ModelUsersApiKeys::inst(), 'ClassUserApiKeyEnliven');

        Verbose::echo1("Errors count: $this->_errorsCount");
    }

    private function _enlivenApiKeys(ModelAbstractApiKeys $mApiKeys, string $classApiKeyEnliven)
    {
        $apiKeysEntity = $mApiKeys::ENTITY;
        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo1("Doing api keys [$apiKeysEntity]");

        $apiKeys = $mApiKeys->getKeysToEnliven(Model::LIMIT_MAX, ['id', 'requestStrId']);
        $apiKeysTotal = count($apiKeys);
        if ($apiKeys) {
            Verbose::echo1("Api keys [$apiKeysEntity] to process: $apiKeysTotal");
            foreach ($apiKeys as $apiKey) {
                try {
                    Verbose::echo2(Verbose::EMPTY_LINE);
                    Verbose::echo1("Doing api key [$apiKeysEntity:%s]", $apiKey['id']);
                    (new $classApiKeyEnliven($apiKey['id']))->run();
                }
                catch (Exception $e) {
                    $this->_handleError($e, $mApiKeys, $apiKey);
                }
            }
        }
        else {
            Verbose::echo1("No api keys [$apiKeysEntity] found");
        }

        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo1("Api keys [$apiKeysEntity] processed: $apiKeysTotal");
    }

    private function _handleError(Exception $e, ModelAbstractApiKeys $mApiKeys, array $apiKey)
    {
        $errCodesMap = [
            self::E_FAILED => [
                'statusCode' => ModelAbstractApiKeys::STATUS_CODE_FAILED,
                'fatal' => true,
            ],
            self::E_INACTIVE_USER => [
                'statusCode' => ModelAbstractApiKeys::STATUS_CODE_FAILED_INACTIVE_USER,
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

        if (Model::inst()->inTransaction()) {
            Model::inst()->rollback();
        }
        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::inst()->disableByStrId($apiKey['requestStrId']);

        $mApiKeys->updateStatus($apiKey['id'], $mApiKeys::STATUS_FAILED, $err['statusCode'], ErrHandler::getFormattedErrMsg($e));

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