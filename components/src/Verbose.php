<?php
/**
 * Usage:
 *      Verbose::init(Verbose::LEVEL_1, true);
 *      Verbose::echo('My message ....');
 */
class Verbose
{
    const LEVEL_0 = 0;
    const LEVEL_1 = 1;
    const LEVEL_2 = 2;
    const LEVEL_3 = 3;

    const VALUES_DELIMITER = "\t";
    const EMPTY_LINE = 'verbose-empty-line-829cc3eb67ad159fbd1718cd38e6298e';
    const LEFT_PADDING = false;

    protected static $_level;
    protected static $_display;
    protected static $_log;
    protected static $_logFile;
    protected static $_initedTs;
    protected static $_inited;

    static function init(int $level = self::LEVEL_0, bool $display = false, bool $log = false, string $logFile = '/var/tmp/verbose.log')
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        static::$_level = $level;
        static::$_display = $display;
        static::$_log = $log;
        static::$_logFile = $logFile;
        static::$_initedTs = microtime(true);
    }

    static function echo($mixedData, int $level = self::LEVEL_1, bool $flush = false, string $caller = null)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($level > static::$_level) {
            return;
        }

        $microTime = explode('.', microtime(true));
        $microSec = isset($microTime[1]) ? sprintf('%04d', $microTime[1]) : '0000';
        $dateTime = strftime("%Y-%m-%d %H:%M:%S." . $microSec, $microTime[0]);
        $timePassed = sprintf('%.4f', microtime(true) - static::$_initedTs);

        $caller = ($caller !== null) ? $caller : Debug::getCaller();
        if ($mixedData === self::EMPTY_LINE) {
            $mixedData = '----------------------------------------------------------------------------------------------';
            $caller = '';
        }

        $leftPadding = '';
        if (self::LEFT_PADDING) {
            $leftPadding = str_repeat(' ', ($level - 1) * 8);
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) && !stristr($_SERVER['HTTP_USER_AGENT'], "curl")) {
            $line = join(self::VALUES_DELIMITER, [
                '<pre style="margin:0;padding:0;">' . "$dateTime ($timePassed)", $leftPadding . print_r($mixedData, 1), $caller . '</pre>'
            ]);
        }
        else {
            $line = join(self::VALUES_DELIMITER, [
                "$dateTime ($timePassed)", $leftPadding . print_r($mixedData, 1), "$caller"
            ]);
        }

        if (static::$_display) {
            if (defined('STDERR')) {
                if (fwrite(STDERR, $line . PHP_EOL) === false) {
                    throw new Err("Failed to write [$line] to STDERR");
                }
            }
            else {
                echo $line . PHP_EOL;
            }

            if ($flush && ob_get_length()) {
                ob_flush();
            }
        }

        if (static::$_log) {
            if (file_put_contents(static::$_logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                throw new Err("Failed to write [$line] to file [%s]", static::$_logFile);
            }
        }
    }

    static function echo1(string $msg, ...$args)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::echo(static::_formatLogNMessage($msg, ...$args), self::LEVEL_1, false, Debug::getCaller());
    }

    static function echo2(string $msg, ...$args)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::echo(static::_formatLogNMessage($msg, ...$args), self::LEVEL_2, false, Debug::getCaller());
    }

    static function echo3(string $msg, ...$args)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::echo(static::_formatLogNMessage($msg, ...$args), self::LEVEL_3, false, Debug::getCaller());
    }

    static function isDisplay(): bool
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::$_display;
    }

    static function getLevel(): int
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return (int) static::$_level;
    }

    static function resetLevel(int $level)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::$_level = $level;
    }

    protected static function _formatLogNMessage(string $msg, ...$args): string
    {
        foreach ($args as &$arg) {
            if (is_array($arg) || is_object($arg)) {
                $arg = Json::encode($arg);
                // Disabled
                //$arg = str_replace('[{', "[\n{", $arg);
                //$arg = str_replace('},', "},\n", $arg);
                //$arg = str_replace('}]', "}\n]", $arg);
            }
        }
        unset($arg);

        $sCount = (int) substr_count($msg, '%s');
        if ($sCount) {
            $sArgs = array_slice($args, 0, $sCount);
            $msg = vsprintf($msg, $sArgs);
        }

        $jArgs = array_slice($args, $sCount);
        $msg .= join(', ', $jArgs);

        return $msg;
    }
}