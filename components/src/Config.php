<?php
/**
 * Usage:
 *      configs/config.php
 *          <?php
 *          return [
 *              'mysql' => [
 *                  'host' => 'localhost',
 *                  ...
 *              ],
 *              ...
 *          ];
 *
 *      Config::init([
 *          ['configs/config.php', true],
 *          ['configs/config.local.php', false]
 *      ]);
 *      Config::get('mysql.host');
 *      Config::set('mysql.host', 'localhost-2');
 */
class Config
{
    // If need to overwrite on merge with empty array parameter
    const OVERWRITE_WITH_EMPTY_ARRAY = 'overwriteWithEmptyArray4c96ed94b84b3c44949b3731cd6c9cfe';

    protected static $_config = [];
    protected static $_inited;

	static function init(array $configFiles)
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }
        foreach ($configFiles as $config) {
            list($file, $required) = $config;
            if (!is_file($file)) {
                if (!$required) {
                    continue;
                }
                throw new Err("Config file not exists [$file]");
            }
            static::$_config = static::_mergeConfigs(
                static::$_config,
                require_once($file)
            );
        }
    }

    static function get(string $name, bool $exception = true)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!strstr($name, '.')) {
            if (!array_key_exists($name, static::$_config)) {
                if (!$exception) {
                    return null;
                }
                throw new Err("No config param [$name]");
            }
            return static::$_config[$name];
        }

        $params = explode('.', $name);

        if (!array_key_exists($params[0], static::$_config)) {
            if (!$exception) {
                return null;
            }
            throw new Err("No config param [$name]");
        }

        $res = static::$_config[$params[0]];
        array_shift($params);

        foreach ($params as $_ => $key) {
            if ($key === '') {
                if (!$exception) {
                    return null;
                }
                throw new Err("Config param [$name] has empty key");
            }
            if (!array_key_exists($key, $res)) {
                if (!$exception) {
                    return null;
                }
                throw new Err("No config param [$name]");
            }
            $res = $res[$key];
        }

        return $res;
    }
	
    static function set(string $name, $value)
	{
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

		if(!strstr($name, ".")) {
            static::$_config[$name] = $value;
			return;
		}

		$param = str_replace(".", "']['", "['" . $name . "']");
        // Set is a rare operation, so eval() can be used here
		eval('return static::$_config' . $param . ' = $value;');
	}

    static function dump(): array
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::$_config;
    }

    protected static function _mergeConfigs(array $cfg1, array $cfg2): array
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $keysList = array_merge(array_keys($cfg1), array_keys($cfg2));
        $res = [];
        foreach($keysList as $k) {
            if(isset($cfg2[$k])) {
                // Scalar
                if(!is_array($cfg2[$k])) {
                    if ($cfg2[$k] === self::OVERWRITE_WITH_EMPTY_ARRAY) {
                        $res[$k] = [];
                    }
                    else {
                        $res[$k] = $cfg2[$k];
                    }
                }
                // Indexed array
                else if(isset($cfg2[$k][0])) {
                    $res[$k] = $cfg2[$k];
                }
                else {
                    $res[$k] = static::_mergeConfigs(isset($cfg1[$k]) ? $cfg1[$k] : [], $cfg2[$k]);
                }
            }
            else {
                $res[$k] = $cfg1[$k];
            }
        }
        return $res;
    }
}
