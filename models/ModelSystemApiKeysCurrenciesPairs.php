<?php
/**
 *
 */
class ModelSystemApiKeysCurrenciesPairs extends ModelAbstractApiKeysCurrenciesPairs
{
    protected $_table = 'systemApiKeysCurrenciesPairs';

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UINT, [1] ],
            'apiKeyId'      => [ Vars::UINT, [1] ],
            'currPairId'    => [ Vars::UINT, [1] ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function insert(int $apiKeyId, int $currPairId): int
    {
        return $this->_insert([
            'apiKeyId' => $apiKeyId,
            'currPairId' => $currPairId,
        ]);
    }
}