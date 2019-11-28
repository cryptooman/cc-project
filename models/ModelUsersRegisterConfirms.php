<?php
/**
 *
 */
class ModelUsersRegisterConfirms extends ModelAbstractRegisterConfirms
{
    protected $_table = 'usersRegisterConfirms';

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UINT, [1] ],
            'confirmKey'    => [ Vars::BASE62, [64, 64] ],
            'userId'        => [ Vars::UINT, [1] ],
            'validTill'     => [ Vars::DATETIME ],
            'confirmed'     => [ Vars::BOOL ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function insert(int $userId, string $confirmKey): int
    {
        return $this->_insert(
            ['userId', $userId],
            $confirmKey,
            Config::get('user.register.confirmRowsPerUserMax'),
            Config::get('user.register.confirmRowExpireMinutes')
        );
    }
}