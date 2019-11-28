<?php
/**
 *
 */
class ModelAdmins extends ModelAbstractUsers
{
    const ROLE_ADMIN     = User::ROLE_ADMIN;
    const ROLE_ADMIN_BOT = User::ROLE_ADMIN_BOT;
    const ROLE_SUDO      = User::ROLE_SUDO;

    protected $_table = 'admins';

    static function passHash($pass, $email)
    {
        return Password::hash($pass, $email, Config::get('admin.auth.pass.hashKey'));
    }

    function __construct()
    {
        $this->_fields = [
            'id'        => [ Vars::UINT, [1] ],
            'email'     => [ Vars::EMAIL, [], function($v) { return strtolower($v); } ],
            'passHash'  => [ Vars::HASH, [64, 64] ],
            'role'      => [ Vars::ENUM, [self::ROLE_ADMIN, self::ROLE_ADMIN_BOT, self::ROLE_SUDO] ],
            'firstName' => [ Vars::REGX, ['!^[\p{L}\'-]{2,20}$!u'] ],
            'lastName'  => [ Vars::REGX, ['!^[\p{L}\'-]{2,20}$!u'] ],
            'approved'  => [ Vars::BOOL ],
            'confirmed' => [ Vars::BOOL ],
            'enabled'   => [ Vars::BOOL ],
            'created'   => [ Vars::DATETIME ],
            'updated'   => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getAdmins(int $limit, array $cols = ['*']): array
    {
        return $this->_getUsers($limit, $cols);
    }

    function getAdminById(int $id, array $cols = ['*']): array
    {
        return $this->_getUserById($id, $cols);
    }

    function getActiveAdminById(int $id, array $cols = ['*']): array
    {
        return $this->_getActiveUserById($id, $cols);
    }

    // NOTE: For authentication action
    function getActiveAdminByCredentials(string $email, string $pass): array
    {
        return $this->_getActiveUserByCredentials($email, self::passHash($pass, $email));
    }

    function insert(string $email, string $pass, string $firstName, string $lastName, string $role = self::ROLE_ADMIN): array
    {
        $passHash = self::passHash($pass, $email);
        $confirmKey = Rand::base62(64);

        $adminId = $this->_insert(
            [
                'email'     => $email,
                'passHash'  => $passHash,
                'role'      => $role,
                'firstName' => $firstName,
                'lastName'  => $lastName
            ],
            $confirmKey,
            ModelAdminsRegisterConfirms::inst()
        );

        return [$adminId, $confirmKey];
    }

    function confirmRegistration(int $id, int $regConfirmId)
    {
        $this->_confirmRegistration($id, $regConfirmId, ModelAdminsRegisterConfirms::inst());
    }
}