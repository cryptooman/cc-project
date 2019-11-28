<?php
/**
 *
 */
class ModelExchangesRequestsStack extends Model
{
    const REQUESTER_TYPE_SYSTEM_API_KEY         = 'systemApiKey';
    const REQUESTER_TYPE_SYSTEM_BALANCE         = 'systemBalance';
    const REQUESTER_TYPE_SYSTEM_BALANCE_BUY     = 'systemBalanceBuy';
    const REQUESTER_TYPE_SYSTEM_BALANCE_SELL    = 'systemBalanceSell'; // Balance "position"
    const REQUESTER_TYPE_USER_API_KEY           = 'userApiKey';
    const REQUESTER_TYPE_USER_BALANCE           = 'userBalance';
    const REQUESTER_TYPE_USER_BALANCE_BUY       = 'userBalanceBuy';
    const REQUESTER_TYPE_USER_BALANCE_SELL      = 'userBalanceSell';
    const REQUESTER_TYPE_ORDER              = 'order';
    const REQUESTER_TYPE_DIRECT_REQUEST     = 'directRequest';

    const STATUS_WAITING    = 'waiting';
    const STATUS_REQUESTING = 'requesting';
    const STATUS_SUCCESS    = 'success';
    const STATUS_FAILED     = 'failed';
    const STATUS_SPECIAL    = 'special';

    const STATUS_CODE_WAITING                   = 0;
    const STATUS_CODE_REQUESTING                = 100;
    const STATUS_CODE_SUCCESS                   = 200;
    const STATUS_CODE_FAILED                    = 300;
    const STATUS_CODE_FAILED_HANGED_REQUEST     = 301;
    const STATUS_CODE_FAILED_BAD_API_KEY        = 302;
    const STATUS_CODE_FAILED_UNALIVE_API_KEY    = 303;
    const STATUS_CODE_SPECIAL                   = 1000;

    const REQUEST_METHOD_GET        = Curl::HTTP_METHOD_GET;
    const REQUEST_METHOD_POST       = Curl::HTTP_METHOD_POST;
    const REQUEST_METHOD_PUT        = Curl::HTTP_METHOD_PUT;
    const REQUEST_METHOD_DELETE     = Curl::HTTP_METHOD_DELETE;
    const REQUEST_METHOD_HEAD       = Curl::HTTP_METHOD_HEAD;

    protected $_table = 'exchangesRequestsStack';

    static private $_requestsBuffer = [];
    static private $_apiKeysSeen = [];

    static function getRequesterTypes(): array
    {
        return [
            self::REQUESTER_TYPE_SYSTEM_API_KEY,
            self::REQUESTER_TYPE_SYSTEM_BALANCE,
            self::REQUESTER_TYPE_SYSTEM_BALANCE_BUY,
            self::REQUESTER_TYPE_SYSTEM_BALANCE_SELL,
            self::REQUESTER_TYPE_USER_API_KEY,
            self::REQUESTER_TYPE_USER_BALANCE,
            self::REQUESTER_TYPE_USER_BALANCE_BUY,
            self::REQUESTER_TYPE_USER_BALANCE_SELL,
            self::REQUESTER_TYPE_ORDER,
            self::REQUESTER_TYPE_DIRECT_REQUEST,
        ];
    }

    static function getStatuses(): array
    {
        return [
            self::STATUS_WAITING,
            self::STATUS_REQUESTING,
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_SPECIAL,
        ];
    }

    static function getStatusCodes(): array
    {
        return [
            self::STATUS_CODE_WAITING,
            self::STATUS_CODE_REQUESTING,
            self::STATUS_CODE_SUCCESS,
            self::STATUS_CODE_FAILED,
            self::STATUS_CODE_FAILED_HANGED_REQUEST,
            self::STATUS_CODE_FAILED_BAD_API_KEY,
            self::STATUS_CODE_FAILED_UNALIVE_API_KEY,
            self::STATUS_CODE_SPECIAL,
        ];
    }

    static function getRequestMethods(): array
    {
        return [
            self::REQUEST_METHOD_GET,
            self::REQUEST_METHOD_POST,
            self::REQUEST_METHOD_PUT,
            self::REQUEST_METHOD_DELETE,
            self::REQUEST_METHOD_HEAD,
        ];
    }

    static function formatVerbose(array $request): array
    {
        if (!$request) {
            return [];
        }
        $request['responseHeaders'] = Str::cutAddDots($request['responseHeaders'], 20);
        $request['responseBody'] = Str::cutAddDots($request['responseBody'], 20);
        return $request;
    }

    function __construct()
    {
        $this->_fields = [
            'id'                    => [ Vars::UBIGINT, [1] ],
            'strId'                 => [ Vars::HASH, [40, 40] ],
            'groupStrId'            => [ Vars::HASH, [40, 40] ],
            'systemApiKeyId'        => [ Vars::UINT ],
            'userApiKeyId'          => [ Vars::UINT ],
            'exchangeId'            => [ Vars::UINT, [1] ],
            'requesterType'         => [ Vars::ENUM, self::getRequesterTypes() ],
            'isApiKeyEnliven'       => [ Vars::BOOL ],
            'isDirectRequest'       => [ Vars::BOOL ],
            'processedByRequester'  => [ Vars::BOOL ],
            'status'                => [ Vars::ENUM, self::getStatuses() ],
            'statusCode'            => [ Vars::ENUM, self::getStatusCodes() ],
            'statusMsg'             => [ Vars::STR, [0], function($v) { return Str::cutAddDots($v, 2048); } ],
            'requestInterfaceIp'    => [ Vars::IP ],
            'requestUrl'            => [ Vars::RAWSTR, [1, 255] ],
            'requestMethod'         => [ Vars::ENUM, self::getRequestMethods() ],
            'requestHeaders'        => [ Vars::RAWSTR, [1, 4096] ],
            'requestData'           => [ Vars::RAWSTR, [1, 4096] ],
            'requestNonce'          => [ Vars::UBIGINT, [1] ],
            'responseCode'          => [ Vars::UINT, [0, 599] ],
            'responseHeaders'       => [ Vars::RAWSTR, [0, 4096] ],
            'responseBody'          => [ Vars::RAWSTR, [0, 102400] ],
            'requestDoneAt'         => [ Vars::DATETIME ],
            'enabled'               => [ Vars::BOOL ],
            'created'               => [ Vars::DATETIME ],
            'updated'               => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    static function makeStrId(string $type, ...$args): string
    {
        $hash = sha1(join('-', array_merge([$type], $args)));
        usleep(1); // In case of ClassDateTime::microTime('') used in $args
        return $hash;
    }

    static function pushRequestToBuffer(array $request)
    {
        if (!$request || !ModelAbstractApiKeys::checkIds($request['systemApiKeyId'], $request['userApiKeyId'], false)) {
            throw new Err("Bad request: ", $request);
        }

        // Short sleep to make different "nonce" for same api keys if exist such
        if (!isset(static::$_apiKeysSeen[ $request['systemApiKeyId'] . '-' . $request['userApiKeyId'] ])) {
            static::$_apiKeysSeen[ $request['systemApiKeyId'] . '-' . $request['userApiKeyId'] ] = true;
        }
        else {
            usleep(1000);
        }

        static::$_requestsBuffer[] = $request;

        Verbose::echo2("Request pushed to buffer: ", $request);
    }

    static function getRequestsBufferLength(): int
    {
        return count(static::$_requestsBuffer);
    }

    static function flushRequestsBuffer()
    {
        if (!static::$_requestsBuffer) {
            throw new Err("Requests buffer is empty");
        }
        static::inst()->insert(static::$_requestsBuffer);
        static::$_requestsBuffer = [];
        static::$_apiKeysSeen = [];
    }

    function getLatestRequests(int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            ORDER BY id DESC 
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getRequestingRequests($limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE status = :status
                  AND processedByRequester = 0 
                  AND enabled = 1
            ORDER BY requestNonce ASC, id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['status' => self::STATUS_REQUESTING]
        )->rows();
    }

    function getRequestById(int $id, array $cols = ['*']): array
    {
        return $this->_getRowById($id, $cols);
    }

    function getWaitingRequests(int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE status = :status
                  AND processedByRequester = 0
                  AND enabled = 1                
            ORDER BY requestNonce ASC, id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['status' => self::STATUS_WAITING]
        )->rows();
    }

    function getUnprocessedRequestByStrId(string $strId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE strId = :strId 
                  AND processedByRequester = 0
                  AND enabled = 1
            ORDER BY id ASC
            LIMIT 1",
            ['%cols%' => $cols],
            ['strId' => $strId]
        )->row();
    }

    function getAndValidateUnprocessedRequestByStrId(string $strId): array
    {
        $req = $this->getUnprocessedRequestByStrId($strId);
        $this->validateUnprocessedRequest($req);
        $req['__inProgress'] = $this->_isRequestInProgress($req['status']);
        return $req;
    }

    function getLatestRequestByStrId(string $strId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE strId = :strId                   
            ORDER BY id DESC
            LIMIT 1",
            ['%cols%' => $cols],
            ['strId' => $strId]
        )->row();
    }

    function insert(array $requests)
    {
        if (!$requests) {
            throw new Err("Empty requests");
        }

        $totalRequests = count($requests);
        foreach ($requests as &$request) {
            if (!ModelAbstractApiKeys::checkIds($request['systemApiKeyId'], $request['userApiKeyId'], false)) {
                throw new Err("Bad request: Must be set systemApiKeyId or userApiKeyId: ", func_get_args());
            }

            if (!isset($request['isApiKeyEnliven'])) {
                $request['isApiKeyEnliven'] = false;
            }
            $request['isApiKeyEnliven'] = (int) $request['isApiKeyEnliven'];

            if (!isset($request['isDirectRequest'])) {
                $request['isDirectRequest'] = false;
            }
            $request['isDirectRequest'] = (int) $request['isDirectRequest'];

            $request = $this->filter($request);
        }
        unset($request);

        $inserted = $this->insertBulk(
            "INSERT INTO $this->_table
            VALUES %values%",
            [
                'DEFAULT',          // id
                ':strId',
                ':groupStrId',
                ':systemApiKeyId',
                ':userApiKeyId',
                ':exchangeId',
                ':requesterType',
                ':isApiKeyEnliven',
                ':isDirectRequest',
                'DEFAULT',          // processedByRequester
                'DEFAULT',          // status
                'DEFAULT',          // statusCode
                'DEFAULT',          // statusMsg
                'DEFAULT',          // requestInterfaceIp
                ':requestUrl',
                ':requestMethod',
                ':requestHeaders',
                ':requestData',
                ':requestNonce',
                '0',                // responseCode
                "''",               // responseHeaders
                "''",               // responseBody
                'DEFAULT',          // requestDoneAt
                'DEFAULT',          // enabled
                'NOW()',            // created
                'DEFAULT',          // updated
            ],
            $requests
        );
        if ($inserted != $totalRequests) {
            throw new Err("Failed to insert rows: ", $requests);
        }
    }

    function updateStatus(int $id, string $status, int $statusCode, string $statusMsg = '')
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set% 
            WHERE id = :id",
            ['%set%' => $this->filter([
                'status' => $status,
                'statusCode' => $statusCode,
                'statusMsg' => $statusMsg,
            ])],
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    function setResponse(int $id, int $responseCode, string $responseHeaders, string $responseBody)
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set%, requestDoneAt = NOW()
            WHERE id = :id",
            ['%set%' => $this->filter([
                'responseCode' => $responseCode,
                'responseHeaders' => $responseHeaders,
                'responseBody' => $responseBody,
            ])],
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    function setProcessedByRequester(int $id)
    {
        $this->query(
            "UPDATE $this->_table 
            SET processedByRequester = 1
            WHERE id = :id
                  AND processedByRequester = 0 
                  AND enabled = 1",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    function setRequestInterfaceIp(int $id, string $ip)
    {
        $this->query(
            "UPDATE $this->_table 
            SET %set%
            WHERE id = :id /* AND enabled = 1 */", // Row can be enabled = 0
            ['%set%' => $this->filter([
                'requestInterfaceIp' => $ip,
            ])],
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    function disableByStrId(string $strId)
    {
        $this->query(
            "UPDATE $this->_table 
            SET enabled = 0
            WHERE strId = :strId AND enabled = 1",
            ['strId' => $strId]
        )->exec()->affectedRows(self::AFFECTED_ANY);
    }

    function disableByStrIds(array $strIds)
    {
        $strIdsIn = [];
        foreach ($strIds as $strId) {
            $strIdsIn[] = $this->quoteValue($this->filterOne('strId', $strId));
        }
        $strIdsIn = join(', ', $strIdsIn);

        $this->query(
            "UPDATE $this->_table 
            SET enabled = 0
            WHERE strId IN($strIdsIn) AND enabled = 1"
        )->exec()->affectedRows(self::AFFECTED_ANY);
    }

    function disableByGroupStrId(string $groupStrId)
    {
        $this->query(
            "UPDATE $this->_table 
            SET enabled = 0
            WHERE groupStrId = :groupStrId AND enabled = 1",
            ['groupStrId' => $groupStrId]
        )->exec()->affectedRows(self::AFFECTED_ANY);
    }

    function validateUnprocessedRequest(array $request)
    {
        if (
            !$request
            || !$request['id']
            || !$request['strId']
            || !$request['exchangeId']
            || !ModelAbstractApiKeys::checkIds($request['systemApiKeyId'], $request['userApiKeyId'], false)
            || (!in_array($request['status'], self::getStatuses()))
            || (!in_array($request['statusCode'], self::getStatusCodes()))
            || $request['processedByRequester']
            || !$request['enabled']
        ) {
            throw new Err("Bad request: ", $request);
        }
        if ($request['status'] == self::STATUS_SPECIAL) {
            throw new Err("Don't know how to use status [%s] of request [%s]", $request['status'], $request['id']);
        }
    }

    private function _isRequestInProgress(string $status): bool
    {
        if (in_array($status, [self::STATUS_WAITING, self::STATUS_REQUESTING])) {
            return true;
        }
        return false;
    }
}