<?php
/**
 *
 */
abstract class ModelAbstractApiKeys extends Model
{
    const ENTITY_SYSTEM = 'system';
    const ENTITY_USER   = 'user';

    const STATUS_NEW        = 'new';
    const STATUS_RENEW      = 'renew';
    const STATUS_CHECKING   = 'checking';
    const STATUS_LIVE       = 'live';
    const STATUS_DISALLOWED = 'disallowed';
    const STATUS_DELETED    = 'deleted';
    const STATUS_FAILED     = 'failed';
    const STATUS_SPECIAL    = 'special';

    const STATUS_CODE_NEW                       = 0;
    const STATUS_CODE_RENEW                     = 100;
    const STATUS_CODE_CHECKING                  = 200;
    const STATUS_CODE_LIVE                      = 300;
    const STATUS_CODE_DISALLOWED                = 400;
    const STATUS_CODE_DELETED                   = 500;
    const STATUS_CODE_FAILED                    = 600;
    const STATUS_CODE_FAILED_BAD_API_KEY        = 601;
    const STATUS_CODE_FAILED_BAD_PERMISSIONS    = 602;
    const STATUS_CODE_FAILED_UNALIVE_API_KEY    = 603;
    const STATUS_CODE_FAILED_INACTIVE_USER      = 604;
    const STATUS_CODE_FAILED_TOO_MANY_FAILS     = 605;
    const STATUS_CODE_SPECIAL                   = 1000;

    // Failed sequence threshold
    // When exceeds, api key status is set to "error"
    const REQUESTS_FAILED_SEQUENCE_MAX = 100;

    static function getStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_RENEW,
            self::STATUS_CHECKING,
            self::STATUS_LIVE,
            self::STATUS_DISALLOWED,
            self::STATUS_DELETED,
            self::STATUS_FAILED,
            self::STATUS_SPECIAL,
        ];
    }

    static function getStatusCodes(): array
    {
        return [
            self::STATUS_CODE_NEW,
            self::STATUS_CODE_RENEW,
            self::STATUS_CODE_CHECKING,
            self::STATUS_CODE_LIVE,
            self::STATUS_CODE_DISALLOWED,
            self::STATUS_CODE_DELETED,
            self::STATUS_CODE_FAILED,
            self::STATUS_CODE_FAILED_TOO_MANY_FAILS,
            self::STATUS_CODE_FAILED_BAD_API_KEY,
            self::STATUS_CODE_FAILED_BAD_PERMISSIONS,
            self::STATUS_CODE_SPECIAL,
        ];
    }

    static function verboseStatusCode(int $code): string
    {
        $verbose = [
            self::STATUS_CODE_NEW                       => 'new',
            self::STATUS_CODE_RENEW                     => 'renew',
            self::STATUS_CODE_CHECKING                  => 'checking',
            self::STATUS_CODE_LIVE                      => 'live',
            self::STATUS_CODE_DISALLOWED                => 'disallowed',
            self::STATUS_CODE_DELETED                   => 'deleted',
            self::STATUS_CODE_FAILED                    => 'failed',
            self::STATUS_CODE_FAILED_TOO_MANY_FAILS     => 'failedTooManyFails',
            self::STATUS_CODE_FAILED_BAD_API_KEY        => 'failedBadApiKey',
            self::STATUS_CODE_FAILED_BAD_PERMISSIONS    => 'failedBadPermissions',
            self::STATUS_CODE_SPECIAL                   => 'special',
        ];
        if (!isset($verbose[$code])) {
            throw new Err("Bad code [$code] to verbose");
        }
        return $verbose[$code];
    }

    static function getModelByIds(int $systemApiKeyId, int $userApiKeyId): ModelAbstractApiKeys
    {
        self::checkIds($systemApiKeyId, $userApiKeyId);
        if ($systemApiKeyId) {
            return ModelSystemApiKeys::inst();
        }
        elseif ($userApiKeyId) {
            return ModelUsersApiKeys::inst();
        }
    }

    static function getModelByEntity(string $entity): ModelAbstractApiKeys
    {
        if ($entity == self::ENTITY_SYSTEM) {
            return ModelSystemApiKeys::inst();
        }
        elseif ($entity == self::ENTITY_USER) {
            return ModelUsersApiKeys::inst();
        }
    }

    static function getKeyId(int $systemApiKeyId, int $userApiKeyId): int
    {
        self::checkIds($systemApiKeyId, $userApiKeyId);
        if ($systemApiKeyId) {
            return $systemApiKeyId;
        }
        elseif ($userApiKeyId) {
            return $userApiKeyId;
        }
    }

    static function checkIds(int $systemApiKeyId, int $userApiKeyId, bool $exception = true): bool
    {
        if (!$systemApiKeyId && !$userApiKeyId) {
            if ($exception) {
                throw new Err("Bad api key id: system [$systemApiKeyId] user [$userApiKeyId]");
            }
            return false;
        }
        elseif ($systemApiKeyId && $userApiKeyId) {
            if ($exception) {
                throw new Err("Bad api key id: system [$systemApiKeyId] user [$userApiKeyId]");
            }
            return false;
        }
        return true;
    }

    static function hash(string $public, string $secret): string
    {
        if (!$public || !$secret) {
            throw new Err("Bad api key data to hash");
        }
        return HashHmac::sha256($public . ' ' . $secret, Config::get('apiKey.hashKey'));
    }

    static function isRenewAllowed(array $apiKey): bool
    {
        if ($apiKey['status'] == self::STATUS_FAILED && $apiKey['enabled']) {
            return true;
        }
        return false;
    }

    function getKeys(int $limit, array $cols = ['*']): array
    {
        return $this->_getRows($limit, $cols);
    }

    function getLiveKeys(int $limit, array $cols = ['*']): array
    {
        return $this->_getLiveKeysByWhere([], $limit, $cols);
    }

    function getKeysToEnliven(int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE status IN(" . $this->_quoteStatusesIn([self::STATUS_NEW, self::STATUS_RENEW, self::STATUS_CHECKING]) . ") 
                  AND enabled = 1
            ORDER BY id ASC 
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getKeysForDirectRequests(int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE status IN(" . $this->_getStatusesInForDirectRequests() . ") 
                  AND enabled = 1
            ORDER BY id ASC 
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getKeyById(int $id, array $cols = ['*']): array
    {
        return $this->_getKeyByWhere(['id' => $id], $cols);
    }

    function getLiveKeyById(int $id, array $cols = ['*']): array
    {
        return $this->_getLiveKeyByWhere(['id' => $id], $cols);
    }

    function getCheckingKeyById(int $id, array $cols = ['*']): array
    {
        return $this->_getKeyByWhere(
            ['id' => $id, 'status' => self::STATUS_CHECKING, 'enabled' => 1], $cols
        );
    }

    function getKeyForDirectRequestsById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE id = :id
                  AND status IN(" . $this->_getStatusesInForDirectRequests() . ") 
                  AND enabled = 1",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    // TODO
    //function isLiveById(int $id): bool
    //{
    //}

    function isLiveBySelf(array $apiKey): bool
    {
        if ($apiKey['status'] == self::STATUS_LIVE && $apiKey['enabled']) {
            return true;
        }
        return false;
    }

    function isValidForDirectRequests(int $id): bool
    {
        return (bool) $this->query(
            "SELECT id
            FROM $this->_table
            WHERE id = :id
                  AND status IN(" . $this->_getStatusesInForDirectRequests() . ") 
                  AND enabled = 1",
            ['id' => $id]
        )->value();
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
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    function updateRequestsStats(int $id, int $requestsTotal, int $requestsFailed, int $requestsFailedSeq, string $requestLastAt)
    {
        if (
            !$requestsTotal
            || $requestsFailed > $requestsTotal
            || $requestsFailedSeq > $requestsTotal
            || $requestsFailedSeq > $requestsFailed
            || !$requestLastAt
        ) {
            throw new Err("Bad api key [$id] stats: ", func_get_args());
        }

        $this->query(
            "UPDATE $this->_table 
            SET %set%
            WHERE id = :id AND enabled = 1",
            ['%set%' => $this->filter([
                'requestsTotal' => $requestsTotal,
                'requestsFailed' => $requestsFailed,
                'requestsFailedSeq' => $requestsFailedSeq,
                'requestLastAt' => $requestLastAt,
            ])],
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);

        if ($requestsFailedSeq > self::REQUESTS_FAILED_SEQUENCE_MAX) {
           $this->updateStatus($id, self::STATUS_FAILED, self::STATUS_CODE_FAILED_TOO_MANY_FAILS);
        }
    }

    function disallow(int $id)
    {
        $this->updateStatus($id, self::STATUS_DISALLOWED, self::STATUS_CODE_DISALLOWED);
    }

    function allow(int $id)
    {
        $this->beginTransaction();

        $this->query(
            "UPDATE $this->_table 
            SET externalAccountId = ''
            WHERE id = :id AND enabled = 1",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ANY);

        $this->updateStatus($id, self::STATUS_RENEW, self::STATUS_CODE_RENEW);

        $this->commit();
    }

    function delete(int $id)
    {
        $this->updateStatus($id, self::STATUS_DELETED, self::STATUS_CODE_DELETED);
    }

    function enable(int $id)
    {
        $this->_enableRowById($id);
    }

    function disable(int $id)
    {
        $this->_disableRowById($id);
    }

    protected function _getKeysByWhere(array $where, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE %where%
            ORDER BY id ASC 
            LIMIT $limit",
            ['%cols%' => $cols],
            ['%where%' => $where]
        )->rows();
    }

    protected function _getLiveKeysByWhere(array $where, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE %where% AND enabled = 1
            ORDER BY id ASC 
            LIMIT $limit",
            ['%cols%' => $cols],
            ['%where%' => array_merge($where, ['status' => self::STATUS_LIVE])]
        )->rows();
    }

    protected function _getKeyByWhere(array $where, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where%",
            ['%cols%' => $cols],
            ['%where%' => $where]
        )->row();
    }

    protected function _getLiveKeyByWhere(array $where, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where% AND enabled = 1",
            ['%cols%' => $cols],
            ['%where%' => array_merge($where, ['status' => self::STATUS_LIVE])]
        )->row();
    }

    protected function _getKeyByHash(string $hash, array $cols = ['*']): array
    {
        return $this->_getKeyByWhere(['hash' => $hash], $cols);
    }

    protected function _insert(array $data): int
    {
        return $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter($data)]
        )->exec()->lastId();
    }

    protected function _getStatusesInForDirectRequests(): string
    {
        return $this->_quoteStatusesIn([
            self::STATUS_NEW,
            self::STATUS_RENEW,
            self::STATUS_CHECKING,
            self::STATUS_LIVE,
            self::STATUS_FAILED,
            self::STATUS_SPECIAL,
        ]);
    }

    protected function _quoteStatusesIn(array $statuses): string
    {
        return $this->_quoteIn($statuses);
    }
}