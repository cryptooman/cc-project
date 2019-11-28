<?php
/**
 *
 */
class ModelSystemApiKeysSettings extends ModelAbstractApiKeysSettings
{
    protected $_table = 'systemApiKeysSettings';

    function __construct()
    {
        $this->_fields = [
            'apiKeyId'              => [ Vars::UINT, [1] ],
            'orderBalanceShareMax'  => [ Vars::UFLOAT, [self::ORDER_BALANCE_SHARE_MIN, self::ORDER_BALANCE_SHARE_MAX] ],
            'created'               => [ Vars::DATETIME ],
            'updated'               => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }
}