<?php
/**
 *
 */
class ModelAdminsAuths extends ModelAbstractAuths
{
    protected $_table = 'adminsAuths';

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UBIGINT, [1] ],
            'authKey'       => [ Vars::BASE64, [128, 128] ],
            'adminId'       => [ Vars::UINT, [1] ],
            'authAttemptId' => [ Vars::UBIGINT, [1] ],
            'validTill'     => [ Vars::DATETIME ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function insert(string $authKey, int $adminId, int $authAttemptId): int
    {
        return $this->_insert(
            ['adminId', $adminId],
            [
                'authKey'       => $authKey,
                'adminId'       => $adminId,
                'authAttemptId' => $authAttemptId,
            ],
            Config::get('admin.auth.session.expireMinutes')
        );
    }

    function disable(int $adminId)
    {
        $this->_disable(['adminId', $adminId]);
    }
}