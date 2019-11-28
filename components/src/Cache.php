<?php
/**
 * Usage:
 *      Cache::init( ... );
 *      $res = Cache::make(
 *          [$arg1, $arg2],
 *          function() use($a, $b) {
 *              return $a + $b;
 *          },
 *          Cache::EXPIRE_HOUR
 *      );
 */
class Cache
{
    const EXPIRE_SEC    = 1;
    const EXPIRE_MINUTE = 60;
    const EXPIRE_HOUR   = 3600;
    const EXPIRE_DAY    = 86400;
    const EXPIRE_NEVER  = 0x12CC0300; // 10 years

    protected static $_dirDefault;
    protected static $_enabled;
    protected static $_inited;

    static function init(string $dirDefault = '/var/tmp/cache', bool $enabled = true)
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        if (!is_dir($dirDefault) || !is_writable($dirDefault)) {
            throw new Err("Bad cache directory [$dirDefault]: Not exists or not writable");
        }
        static::$_dirDefault = $dirDefault;

        static::$_enabled = $enabled;
    }

    static function make(array $cacheKey, $callback, int $expireSec, string $dir = null, bool $reset = false)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($dir && (!is_dir($dir) || !is_writable($dir))) {
            throw new Err("Bad cache directory [$dir]: Not exists or not writable");
        }
        else {
            $dir = static::$_dirDefault;
        }

        $data = Cache::get($cacheKey, $dir);
        if ($data === false || $reset) {
            $data = $callback();
            Cache::set($data, $cacheKey, $expireSec, $dir);
        }

        return $data;
    }

    static function get(array $cacheKey, string $dir = null)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($dir && !is_dir($dir)) {
            throw new Err("Cache directory [$dir] not exists");
        }
        else {
            $dir = static::$_dirDefault;
        }

        if (!static::$_enabled) {
            return false;
        }

        $cacheFile = $dir . '/' . static::_cacheKeyToFileName($cacheKey);
        if (!is_file($cacheFile)) {
            return false;
        }

        $mtime = @filemtime($cacheFile);
        if ($mtime === false) {
            return false;
        }
        if ($mtime < time()) {
            return false;
        }

        if (($data = file_get_contents($cacheFile)) === false) {
            throw new Err("Failed to read cache file [$cacheFile]");
        }

        return Json::decode($data);
    }

    static function set($data, array $cacheKey, int $expireSec, string $dir = null)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($dir && (!is_dir($dir) || !is_writable($dir))) {
            throw new Err("Bad cache directory [$dir]: Not exists or not writable");
        }
        else {
            $dir = static::$_dirDefault;
        }

        $cacheFile = $dir . '/' . static::_cacheKeyToFileName($cacheKey);

        if (file_put_contents($cacheFile, Json::encode($data), LOCK_EX) === false) {
            throw new Err("Failed to write cache file [$cacheFile] with data: ", $data);
        }
        if (touch($cacheFile, time() + $expireSec) === false) {
            throw new Err("Failed to set expire lifetime to cache file [$cacheFile]");
        }
    }

    static function delete(array $cacheKey, string $dir = null)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($dir && (!is_dir($dir) || !is_writable($dir))) {
            throw new Err("Bad cache directory [$dir]: Not exists or not writable");
        }
        else {
            $dir = static::$_dirDefault;
        }

        $cacheFile = $dir . '/' . static::_cacheKeyToFileName($cacheKey);
        if (!unlink($cacheFile)) {
            throw new Err("Failed to delete cache file [$cacheFile]");
        }
    }

    protected static function _cacheKeyToFileName(array $cacheKey)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $fileName = F::argsToTransliteratedStr($cacheKey, '__', 200) . '.cache';
        if (strlen($fileName) > Cnst::FILE_NAME_LENGTH_MAX) {
            throw new Err("Bad cache file name [$fileName]: Too long");
        }
        return $fileName;
    }
}

