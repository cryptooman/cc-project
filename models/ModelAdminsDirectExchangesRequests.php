<?php
/**
 *
 */
class ModelAdminsDirectExchangesRequests extends Model
{
    const REQUESTER_TYPE = ModelExchangesRequestsStack::REQUESTER_TYPE_DIRECT_REQUEST;

    const TYPE_GET_ORDERS       = 'getOrders';
    const TYPE_GET_ORDER        = 'getOrder';
    const TYPE_GET_POSITIONS    = 'getPositions';
    const TYPE_GET_BALANCES     = 'getBalances';
    const TYPE_ORDER_NEW        = 'orderNew';
    const TYPE_ORDER_REPLACE    = 'orderReplace';
    const TYPE_ORDER_CANCEL     = 'orderCancel';
    const TYPE_API_REQUEST      = 'apiRequest';

    const STATUS_PENDING        = 'pending';
    const STATUS_REQUESTING     = 'requesting';
    const STATUS_SUCCESS        = 'success';
    const STATUS_FAILED         = 'failed';
    const STATUS_SPECIAL        = 'special';

    const STATUS_CODE_PENDING    = 0;
    const STATUS_CODE_REQUESTING = 100;
    const STATUS_CODE_SUCCESS    = 200;
    const STATUS_CODE_FAILED     = 300;
    const STATUS_CODE_SPECIAL    = 1000;

    protected $_table = 'adminsDirectExchangesRequests';

    static function getTypes()
    {
        return [
            self::TYPE_GET_ORDERS,
            self::TYPE_GET_ORDER,
            self::TYPE_GET_POSITIONS,
            self::TYPE_GET_BALANCES,
            self::TYPE_ORDER_NEW,
            self::TYPE_ORDER_REPLACE,
            self::TYPE_ORDER_CANCEL,
            self::TYPE_API_REQUEST,
        ];
    }

    static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_REQUESTING,
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_SPECIAL,
        ];
    }

    static function getStatusCodes(): array
    {
        return [
            self::STATUS_CODE_PENDING,
            self::STATUS_CODE_REQUESTING,
            self::STATUS_CODE_SUCCESS,
            self::STATUS_CODE_FAILED,
            self::STATUS_CODE_SPECIAL,
        ];
    }

    function __construct()
    {
        $this->_fields = [
            'id'                => [ Vars::UBIGINT, [1] ],
            'adminId'           => [ Vars::UINT, [1] ],
            'systemApiKeyId'    => [ Vars::UINT ],
            'userApiKeyId'      => [ Vars::UINT ],
            'exchangeId'        => [ Vars::UINT, [1] ],
            'requestStrId'      => [ Vars::HASH, [40, 40] ],
            'type'              => [ Vars::ENUM, self::getTypes() ],
            'status'            => [ Vars::ENUM, self::getStatuses() ],
            'statusCode'        => [ Vars::ENUM, self::getStatusCodes() ],
            'statusMsg'         => [ Vars::STR, [0], function($v) { return Str::cutAddDots($v, 2048); } ],
            'requestData'       => [ Vars::RAWSTR, [0, 255] ],
            'response'          => [ Vars::RAWSTR, [0, 102400] ],
            'enabled'           => [ Vars::BOOL ],
            'created'           => [ Vars::DATETIME ],
            'updated'           => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getActiveRequests(int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE status IN(" . $this->_getActiveStatusesIn() . ")
                  AND enabled = 1
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    function getActiveRequestById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE id = :id
                  AND status IN(" . $this->_getActiveStatusesIn() . ") 
                  AND enabled = 1",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    function getCompletedRequestById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE id = :id
                  AND status IN(" . $this->_getCompletedStatusesIn() . ")
                  AND enabled = 1",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    function insert(int $adminId, int $systemApiKeyId, int $userApiKeyId, int $exchangeId, string $type, array $requestData = []): int
    {
        ModelAbstractApiKeys::checkIds($systemApiKeyId, $userApiKeyId);

        $requestStrId = ModelExchangesRequestsStack::makeStrId(self::REQUESTER_TYPE, ClassDateTime::microTime(''));

        return $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter([
                'adminId'           => $adminId,
                'systemApiKeyId'    => $systemApiKeyId,
                'userApiKeyId'      => $userApiKeyId,
                'exchangeId'        => $exchangeId,
                'requestStrId'      => $requestStrId,
                'type'              => $type,
                'requestData'       => Json::encode($requestData),
                'response'          => '',
            ])]
        )->exec()->lastId();
    }

    function setRequesting(int $id, array $requestToStack)
    {
        ModelExchangesRequestsStack::pushRequestToBuffer($requestToStack);

        Model::inst()->beginTransaction();

        ModelExchangesRequestsStack::flushRequestsBuffer();

        ModelAdminsDirectExchangesRequests::inst()->updateStatus(
            $id, ModelAdminsDirectExchangesRequests::STATUS_REQUESTING, ModelAdminsDirectExchangesRequests::STATUS_CODE_REQUESTING
        );

        Model::inst()->commit();
    }

    function setSuccessResponse(
        int $id, string $response, int $requestStackId, int $statusCode = self::STATUS_CODE_SUCCESS, string $statusMsg = ''
    )
    {
        $this->beginTransaction();

        $this->query(
            "UPDATE $this->_table 
            SET response = :response
            WHERE id = :id
                  AND status NOT IN(" . $this->_getCompletedStatusesIn() . ")
                  AND enabled = 1",
            $this->filter([
                'response' => $response,
            ]),
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);

        $this->updateStatus($id, self::STATUS_SUCCESS, $statusCode, $statusMsg);

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($requestStackId);

        $this->commit();
    }

    function setFailedResponse(
        int $id, string $response, int $requestStackId, int $statusCode = self::STATUS_CODE_FAILED, string $statusMsg = ''
    )
    {
        $this->beginTransaction();

        $this->query(
            "UPDATE $this->_table 
            SET response = :response
            WHERE id = :id
                  AND status NOT IN(" . $this->_getCompletedStatusesIn() . ")
                  AND enabled = 1",
            $this->filter([
                'response' => $response,
            ]),
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);

        $this->updateStatus($id, self::STATUS_FAILED, $statusCode, $statusMsg);

        ModelExchangesRequestsStack::inst()->setProcessedByRequester($requestStackId);

        $this->commit();
    }

    function setError(int $id, string $requestStrId, string $error, int $statusCode = ModelAdminsDirectExchangesRequests::STATUS_CODE_FAILED)
    {
        Model::inst()->beginTransaction();

        ModelAdminsDirectExchangesRequests::inst()->updateStatus(
            $id, ModelAdminsDirectExchangesRequests::STATUS_FAILED, $statusCode, $error
        );

        ModelExchangesRequestsStack::inst()->disableByStrId($requestStrId);

        Model::inst()->commit();
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

    private function _getActiveStatusesIn(): string
    {
        return join(', ', [
            $this->quoteValue(self::STATUS_PENDING),
            $this->quoteValue(self::STATUS_REQUESTING),
        ]);
    }

    private function _getCompletedStatusesIn(): string
    {
        return join(', ', [
            $this->quoteValue(self::STATUS_SUCCESS),
            $this->quoteValue(self::STATUS_FAILED),
        ]);
    }
}