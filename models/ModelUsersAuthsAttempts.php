<?php
/**
 *
 */
class ModelUsersAuthsAttempts extends ModelAbstractAuthsAttempts
{
    protected $_table = 'usersAuthsAttempts';

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UBIGINT, [1] ],
            'hash'          => [ Vars::HASH, [64, 64] ],
            'email'         => [ Vars::EMAIL, [], function($v) { return strtolower($v); } ],
            'ip'            => [ Vars::IP ],
            'browserStr'    => [ Vars::STR, [10, 255] ],
            'validTill'     => [ Vars::DATETIME ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function insert(string $hash, string $email, string $ip, string $browserStr): int
    {
        return $this->_insert(
            [
                'hash'       => $hash,
                'email'      => $email,
                'ip'         => $ip,
                'browserStr' => $browserStr,
            ],
            Config::get('user.auth.limits.attemptRowExpireMinutes')
        );
    }
}