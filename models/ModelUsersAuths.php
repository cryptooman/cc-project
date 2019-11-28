<?php
/**
 *
 */
class ModelUsersAuths extends ModelAbstractAuths
{
    protected $_table = 'usersAuths';

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UBIGINT, [1] ],
            'authKey'       => [ Vars::BASE64, [128, 128] ],
            'userId'        => [ Vars::UINT, [1] ],
            'authAttemptId' => [ Vars::UBIGINT, [1] ],
            'validTill'     => [ Vars::DATETIME ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function insert(string $authKey, int $userId, int $authAttemptId): int
    {
        return $this->_insert(
            ['userId', $userId],
            [
                'authKey'       => $authKey,
                'userId'        => $userId,
                'authAttemptId' => $authAttemptId,
            ],
            Config::get('user.auth.session.expireMinutes')
        );
    }

    function disable(int $userId)
    {
        $this->_disable(['userId', $userId]);
    }
}