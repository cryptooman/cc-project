<?php
/**
 *
 */
class ModelUsersApiKeysCurrenciesPairs extends ModelAbstractApiKeysCurrenciesPairs
{
    protected $_table = 'usersApiKeysCurrenciesPairs';

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UINT, [1] ],
            'userId'        => [ Vars::UINT, [1] ],
            'apiKeyId'      => [ Vars::UINT, [1] ],
            'currPairId'    => [ Vars::UINT, [1] ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function insert(int $userId, int $apiKeyId, int $currPairId): int
    {
        return $this->_insert([
            'userId' => $userId,
            'apiKeyId' => $apiKeyId,
            'currPairId' => $currPairId,
        ]);
    }
}