<?php
/**
 * Usage:
 *      ...
 */
class Cookie
{
    const EXPIRE_SESSION = 0;
    const EXPIRE_HOUR = 60;
    const EXPIRE_DAY = 86400;
    const EXPIRE_NEVER = 0x12CC0300; // 10 years

    protected static $_expireSecDefault;
    protected static $_domainDefault;
    protected static $_httpsOnlyDefault;
    protected static $_denyJsAccessDefault;
    protected static $_inited;

    static function init(
        int $expireSecDefault = self::EXPIRE_NEVER, string $domainDefault = '', bool $httpsOnlyDefault = false, bool $denyJsAccessDefault = true
    )
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        static::$_expireSecDefault = $expireSecDefault;
        static::$_domainDefault = $domainDefault;
        static::$_httpsOnlyDefault = $httpsOnlyDefault;
        static::$_denyJsAccessDefault = $denyJsAccessDefault;
    }

    static function get(string $name)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!array_key_exists($name, $_COOKIE)) {
            return null;
        }
        $value = $_COOKIE[$name];
        return $value;
    }

    static function set(
        string $name, $value, int $expireSec = null, string $domain = null, bool $httpsOnly = null, bool $denyJsAccess = null
    )
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($expireSec === null) {
            $expireSec = static::$_expireSecDefault;
        }
        $expireUtime = ($expireSec > 0) ? time() + $expireSec : $expireSec;

        if ($domain === null) {
            $domain = static::$_domainDefault;
        }
        if (!$domain) {
            $domain = null;
        }

        if ($httpsOnly === null) {
            $httpsOnly = static::$_httpsOnlyDefault;
        }

        if ($denyJsAccess === null) {
            $denyJsAccess = static::$_denyJsAccessDefault;
        }

        if (!setcookie($name, $value, $expireUtime, $path = '/', $domain, $httpsOnly, $denyJsAccess)) {
            throw new Err("Unable to set cookie [$name] with value [$value]");
        }
        $_COOKIE[$name] = $value;
    }

    static function delete($name, string $domain = null, bool $httpsOnly = null, bool $denyJsAccess = null)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!array_key_exists($name, $_COOKIE)) {
            return;
        }

        if ($domain === null) {
            $domain = static::$_domainDefault;
        }
        if (!$domain) {
            $domain = null;
        }

        if ($httpsOnly === null) {
            $httpsOnly = static::$_httpsOnlyDefault;
        }

        if ($denyJsAccess === null) {
            $denyJsAccess = static::$_denyJsAccessDefault;
        }

        $expireUtime = 1;
        if (!setcookie($name, '', $expireUtime, $path = '/', $domain, $httpsOnly, $denyJsAccess)) {
            throw new Err("Unable to delete cookie [$name]");
        }
        unset($_COOKIE[$name]);
    }

    static function formatForJs($name, $value, int $expireSec = null, string $domain = null): array
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($expireSec === null) {
            $expireSec = static::$_expireSecDefault;
        }
        $expireUtime = ($expireSec > 0) ? time() + $expireSec : $expireSec;

        $domain = ($domain !== null) ? $domain : static::$_domainDefault;
        if (!$domain) {
            $domain = null;
        }

        $jsSetCookieData = [
            'name'          => $name,
            'value'         => $value,
            'expires'       => $expireUtime,
            'expiresUTCStr' => gmdate('D, d-M-Y H:i:s', $expireUtime).', GMT',
            'lifetime'      => $expireSec,
        ];
        if ($domain) {
            $jsSetCookieData['domain'] = $domain;
        }
        return $jsSetCookieData;
    }
}
