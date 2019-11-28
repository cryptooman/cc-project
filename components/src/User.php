<?php
/**
 *
 */
class User
{
    const ROLE_USER      = 'user';       // Authenticated user
    const ROLE_USER_BOT  = 'userBot';    // Bot user
    const ROLE_ADMIN     = 'admin';      // Authenticated user with admin permissions
    const ROLE_ADMIN_BOT = 'adminBot';   // Bot user with admin permissions
    const ROLE_SUDO      = 'sudo';       // Authenticated user with sudo permissions

    protected static $_id = 0;      // "0" for a guest user (i.e. non-authenticated)
    protected static $_role = '';   // "" for a guest user
    protected static $_info = [];

    static function set(int $id, string $role, array $info = [])
    {
        if (!$id || !$role) {
            throw new Err("Bad data to set user");
        }
        if (!in_array($role, [self::ROLE_USER, self::ROLE_ADMIN, self::ROLE_SUDO])) {
            throw new Err("Bad user role [$role]");
        }
        static::$_id = $id;
        static::$_role = $role;
        static::$_info = $info;
    }

    static function id(): int
    {
        return static::$_id;
    }

    static function role(): string
    {
        return static::$_role;
    }

    static function authed(): bool
    {
        return (bool) static::$_id;
    }

    static function hasUserAccess(): bool
    {
        if (in_array(
            static::$_role, [
                User::ROLE_USER,
                User::ROLE_USER_BOT,
                User::ROLE_ADMIN,
                User::ROLE_ADMIN_BOT,
                User::ROLE_SUDO
            ], true
        )) {
            return true;
        }
        return false;
    }

    static function hasAdminAccess(): bool
    {
        if (in_array(
            static::$_role, [
                User::ROLE_ADMIN,
                User::ROLE_ADMIN_BOT,
                User::ROLE_SUDO
            ], true
        )) {
            return true;
        }
        return false;
    }

    static function hasSudoAccess(): bool
    {
        if (static::$_role === User::ROLE_SUDO) {
            return true;
        }
        return false;
    }

    static function info(string $name = '')
    {
        if (!$name) {
            return static::$_info;
        }
        if (!isset(static::$_info[$name])) {
            throw new Err("Unknown user info [$name]");
        }
        return static::$_info[$name];
    }
}