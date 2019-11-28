<?php
/**
 *
 */
class ModelUsers extends ModelAbstractUsers
{
    const ROLE_USER = User::ROLE_USER;

    protected $_table = 'users';

    static function passHash($pass, $email)
    {
        return Password::hash($pass, $email, Config::get('user.auth.pass.hashKey'));
    }

    function __construct()
    {
        $this->_fields = [
            'id'            => [ Vars::UINT, [1] ],
            'urlId'         => [ Vars::URLID, [20, 20] ],
            'email'         => [ Vars::EMAIL, [], function($v) { return strtolower($v); } ],
            'passHash'      => [ Vars::HASH, [64, 64] ],
            'role'          => [ Vars::ENUM, [self::ROLE_USER] ],
            'firstName'     => [ Vars::REGX, ['!^[\p{L}\'-]{2,20}$!u'] ],
            'lastName'      => [ Vars::REGX, ['!^[\p{L}\'-]{2,20}$!u'] ],
            'middleName'    => [ Vars::REGX, ['!^[\p{L}\'-]{2,20}$!u'] ],
            'approved'      => [ Vars::BOOL ],
            'confirmed'     => [ Vars::BOOL ],
            'flag'          => [ Vars::INT ],
            'enabled'       => [ Vars::BOOL ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getUsers(int $limit, array $cols = ['*']): array
    {
        return $this->_getUsers($limit, $cols);
    }

    function getUserById(int $id, array $cols = ['*']): array
    {
        return $this->_getUserById($id, $cols);
    }

    function getActiveUserById(int $id, array $cols = ['*']): array
    {
        return $this->_getActiveUserById($id, $cols);
    }

    // NOTE: For authentication action
    function getActiveUserByCredentials(string $email, string $pass): array
    {
        return $this->_getActiveUserByCredentials($email, self::passHash($pass, $email));
    }

    function insert(
        string $email, string $pass, string $firstName, string $lastName, string $middleName, string $role = self::ROLE_USER
    ): array
    {
        // Sample: PC-686937-4750450453
        $urlId = chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . '-' . Rand::int(100000, 999999) . '-' . Rand::int(1000000000, 9999999999);
        $passHash = self::passHash($pass, $email);
        $confirmKey = Rand::base62(64);

        $userId = $this->_insert(
            [
                'urlId'         => $urlId,
                'email'         => $email,
                'passHash'      => $passHash,
                'role'          => $role,
                'firstName'     => $firstName,
                'lastName'      => $lastName,
                'middleName'    => $middleName,
            ],
            $confirmKey,
            ModelUsersRegisterConfirms::inst()
        );

        return [$userId, $urlId, $confirmKey];
    }

    function confirmRegistration(int $id, int $regConfirmId)
    {
        $this->_confirmRegistration($id, $regConfirmId, ModelUsersRegisterConfirms::inst());
    }
}