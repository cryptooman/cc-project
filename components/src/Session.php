<?php
/**
 * Usage:
 *      Session::init( ... );
 *      Session::start();
 *      ...
 */
class Session
{
	const NAMESPACE_DEFAULT = "default63c03cdf23ac63280e0b41de34788236";

    protected static $_cookieName;
    protected static $_cookieDomain;
    protected static $_cookieHttpsOnly;
    protected static $_cookieDenyJsAccess;
    protected static $_namespaces = [];
    protected static $_started = false;
    protected static $_inited;

	static function init(
	    string $cookieName = 'sessid', string $cookieDomain = '', bool $cookieHttpsOnly = false, bool $cookieDenyJsAccess = true
    )
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        if (!$cookieName) {
            throw new Err("Session cookie name is empty");
        }
        static::$_cookieName = $cookieName;

        static::$_cookieDomain = $cookieDomain;
        static::$_cookieHttpsOnly = $cookieHttpsOnly;
        static::$_cookieDenyJsAccess = $cookieDenyJsAccess;
    }

    static function start()
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (static::$_started) {
            throw new Err("Session was already started");
        }
        if (!session_start([
            'name'              => static::$_cookieName,
            'cookie_domain'     => static::$_cookieDomain,
            'cookie_secure'     => static::$_cookieHttpsOnly,
            'cookie_httponly'   => static::$_cookieDenyJsAccess,
            'cookie_path'       => '/',
            'cookie_lifetime'   => 0,
            'gc_maxlifetime'    => 86400,
            'use_cookies'       => true,
            'use_only_cookies'  => true,
            'use_strict_mode'   => true,
        ])) {
            throw new Err("Unable to start session");
        }
        static::$_started = true;
    }

	static function get(string $name, string $namespace = self::NAMESPACE_DEFAULT)
	{
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_started) {
            throw new Err("Session was not started");
        }
		if (!isset($_SESSION[$namespace][$name])) {
		    return null;
        }
		return $_SESSION[$namespace][$name];
	}
	
	static function set(string $name, $value, string $namespace = self::NAMESPACE_DEFAULT)
	{
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_started) {
            throw new Err("Session was not started");
        }
		$_SESSION[$namespace][$name] = $value;
        static::$_namespaces[$namespace] = true;
	}

	static function delete(string $name, string $namespace = self::NAMESPACE_DEFAULT)
	{
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_started) {
            throw new Err("Session was not started");
        }
		if (isset($_SESSION[$namespace][$name])) {
            unset($_SESSION[$namespace][$name]);
        }
	}
	
	static function deleteNamespace(string $namespace)
	{
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_started) {
            throw new Err("Session was not started");
        }
		if (isset($_SESSION[$namespace])) {
            unset($_SESSION[$namespace]);
        }
	}

    static function commit()
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_started) {
            throw new Err("Session was not started");
        }
        session_write_close();
        static::$_started = false;
    }

    static function dump(string $namespace = self::NAMESPACE_DEFAULT): array
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_started) {
            throw new Err("Session was not started");
        }
        if (!isset($_SESSION[$namespace])) {
            return null;
        }
        return $_SESSION[$namespace];
    }

	static function destroy()
	{
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_started) {
            throw new Err("Session was not started");
        }
        foreach (static::$_namespaces as $namespace) {
            if (isset($_SESSION[$namespace])) {
                unset($_SESSION[$namespace]);
            }
        }
		if (!session_destroy()) {
			throw new Err("Unable to destroy session");
		}
        if (!setcookie(static::$_cookieName, '', 1, '/', static::$_cookieDomain)) {
            throw new Err("Unable to delete session cookie [%s]", static::$_cookieName);
        }
        unset($_COOKIE[static::$_cookieName]);
	}
}