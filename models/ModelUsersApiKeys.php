<?php
/**
 *
 */
class ModelUsersApiKeys extends ModelAbstractApiKeys
{
    const ENTITY = self::ENTITY_USER;
    const REQUESTER_TYPE = ModelExchangesRequestsStack::REQUESTER_TYPE_USER_API_KEY;

    protected $_table = 'usersApiKeys';

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UINT, [1] ],
            'hash'              => [ Vars::HASH, [64, 64] ],
            'urlId'             => [ Vars::BASE62, [32, 32] ],
            'userId'            => [ Vars::UINT, [1] ],
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

    function getKeysByUserId(int $userId, int $limit, array $cols = ['*']): array
    {
        return $this->_getKeysByWhere(['userId' => $userId], $limit, $cols);
    }

    function getKeysForUserListingByUserId(int $userId, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE userId = :userId AND status != :status
            ORDER BY id ASC 
            LIMIT $limit",
            ['%cols%' => $cols],
            ['userId' => $userId, 'status' => self::STATUS_DELETED]
        )->rows();
    }

    function getLiveKeyByIdUserId(int $id, int $userId, array $cols = ['*']): array
    {
        return $this->_getLiveKeyByWhere(['id' => $id, 'userId' => $userId], $cols);
    }

    function getKeyByHashAndUserId(string $hash, int $userId, array $cols = ['*']): array
    {
        return $this->_getKeyByWhere(['hash' => $hash, 'userId' => $userId], $cols);
    }

    function getKeyByUrlIdUserId(string $urlId, int $userId, array $cols = ['*']): array
    {
        return $this->_getKeyByWhere(['urlId' => $urlId, 'userId' => $userId], $cols);
    }

    function countKeysByUserId(int $userId): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) 
            FROM $this->_table
            WHERE userId = :userId",
            ['userId' => $userId]
        )->value();
    }

    function insert(int $userId, int $exchangeId, string $public, string $secret, string $name, array $currPairIds): array
    {
        if (!$currPairIds) {
            throw new Err("Empty currPairIds");
        }

        $rows = $this->countKeysByUserId($userId);
        if ($rows >= Config::get('user.apiKey.addedKeysMax')) {
            throw new Err("Max allowed added api keys [$rows] reached for user [$userId]");
        }

        $hash = self::hash($public, $secret);
        $urlId = Rand::base62(32);
        $secretEncrypted = ClassUserApiKey::encryptApiSecret($secret, $userId);

        $requestStrId = ModelExchangesRequestsStack::makeStrId(self::REQUESTER_TYPE, $userId, ClassDateTime::microTime(''));

        $this->beginTransaction();

        $apiKeyId = $this->_insert([
            'hash'              => $hash,
            'urlId'             => $urlId,
            'userId'            => $userId,
            'exchangeId'        => $exchangeId,
            'public'            => $public,
            'secretEncrypted'   => $secretEncrypted,
            'name'              => $name,
            'requestStrId'      => $requestStrId,
        ]);

        ModelUsersApiKeysSettings::inst()->insert($apiKeyId);

        foreach ($currPairIds as $pairId) {
            ModelUsersApiKeysCurrenciesPairs::inst()->insert($userId, $apiKeyId, $pairId);
        }

        ModelUsersBalances::inst()->createNewBalances($userId, $apiKeyId);

        $this->commit();

        return [$apiKeyId, $urlId];
    }

    function addNewCurrPair(int $currId, int $currPairId)
    {
        $apiKeys = $this->getKeys(self::LIMIT_MAX, ['id', 'userId']);
        if (!$apiKeys) {
            return;
        }
        $this->beginTransaction();
        foreach ($apiKeys as $apiKey) {
            ModelUsersApiKeysCurrenciesPairs::inst()->insert($apiKey['userId'], $apiKey['id'], $currPairId);
            ModelUsersBalances::inst()->createCurrencyNewBalances($apiKey['userId'], $apiKey['id'], $currId);
        }
        $this->commit();
    }
}