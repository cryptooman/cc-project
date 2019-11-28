<?php
/**
 *
 */
class ModelAdminsRegisterConfirms extends ModelAbstractRegisterConfirms
{
    protected $_table = 'adminsRegisterConfirms';

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UINT, [1] ],
            'confirmKey'    => [ Vars::BASE62, [64, 64] ],
            'adminId'       => [ Vars::UINT, [1] ],
            'validTill'     => [ Vars::DATETIME ],
            'confirmed'     => [ Vars::BOOL ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function insert(int $adminId, string $confirmKey): int
    {
        return $this->_insert(
            ['adminId', $adminId],
            $confirmKey,
            Config::get('admin.register.confirmRowsPerAdminMax'),
            Config::get('admin.register.confirmRowExpireMinutes')
        );
    }
}