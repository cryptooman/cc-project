<?php
/**
 *
 */
class ModelSystemApiKeys extends ModelAbstractApiKeys
{
    const ENTITY = self::ENTITY_SYSTEM;
    const REQUESTER_TYPE = ModelExchangesRequestsStack::REQUESTER_TYPE_SYSTEM_API_KEY;

    protected $_table = 'systemApiKeys';

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UINT, [1] ],
            'hash'              => [ Vars::HASH, [64, 64] ],
            'exchangeId'        => [ Vars::UINT, [1] ],
            'public'            => [ Vars::REGX, ['!^[a-zA-Z0-9_\-]{32,255}$!'] ],
            'secretEncrypted'   => [ Vars::BASE64, [344, 344] ],
            'name'              => [ Vars::MBSTR, [1, 50] ],
            'requestStrId'      => [ Vars::HASH, [40, 40] ],
            'externalAccountId' => [ Vars::REGX, ['!^[a-zA-Z0-9_\-]{0,64}$!'] ],
            'status'            => [ Vars::ENUM, self::getStatuses() ],
            'statusCode'        => [ Vars::ENUM, self::getStatusCodes() ],
            'statusMsg'         => [ Vars::STR, [0], function($v) { return Str::cutAddDots($v, 2048); } ],
            'requestsTotal'     => [ Vars::UINT ],
            'requestsFailed'    => [ Vars::UINT ],
            'requestsFailedSeq' => [ Vars::UINT ],
            'requestLastAt'     => [ Vars::DATETIME ],
            'flag'              => [ Vars::INT ],
            'enabled'           => [ Vars::BOOL ],
            'created'           => [ Vars::DATETIME ],
            'updated'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getKeyByHash(string $hash, array $cols = ['*']): array
    {
        return $this->_getKeyByHash($hash, $cols);
    }

    function insert(int $exchangeId, string $public, string $secret, string $name, array $currPairIds): int
    {
        if (!$currPairIds) {
            throw new Err("Empty currPairIds");
        }

        $hash = self::hash($public, $secret);
        $secretEncrypted = ClassSystemApiKey::encryptApiSecret($secret);

        $requestStrId = ModelExchangesRequestsStack::makeStrId(self::REQUESTER_TYPE, ClassDateTime::microTime(''));

        $this->beginTransaction();

        $apiKeyId = $this->_insert([
            'hash'              => $hash,
            'exchangeId'        => $exchangeId,
            'public'            => $public,
            'secretEncrypted'   => $secretEncrypted,
            'name'              => $name,
            'requestStrId'      => $requestStrId,
        ]);

        ModelSystemApiKeysSettings::inst()->insert($apiKeyId);

        foreach ($currPairIds as $pairId) {
            ModelSystemApiKeysCurrenciesPairs::inst()->insert($apiKeyId, $pairId);
        }

        ModelSystemBalances::inst()->createNewBalances($apiKeyId);

        $this->commit();

        return $apiKeyId;
    }

    function addNewCurrPair(int $currId, int $currPairId)
    {
        $apiKeys = $this->getKeys(self::LIMIT_MAX, ['id']);
        if (!$apiKeys) {
            return;
        }
        $this->beginTransaction();
        foreach ($apiKeys as $apiKey) {
            ModelSystemApiKeysCurrenciesPairs::inst()->insert($apiKey['id'], $currPairId);
            ModelSystemBalances::inst()->createCurrencyNewBalances($apiKey['id'], $currId);
        }
        $this->commit();
    }
}