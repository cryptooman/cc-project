<?php
/**
 * Usage:
 *      Log::init( ... );
 *      Log::write('test', 'Test ...');
 *      Log::write('test', ['Test 1', 'Test 2']);
 *      ...
 */
class Log
{
    const TYPE_SIMPLE = 'simple';
    const TYPE_EXTENDED = 'extended';
    const VALUES_DELIMITER = "\t";

    protected static $_dirDefault;
    protected static $_initedTs;
    protected static $_inited;

    static function init(string $dirDefault = '/var/log')
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        if (!is_dir($dirDefault) || !is_writable($dirDefault)) {
            throw new Err("Bad log directory [$dirDefault]: Not exists or not writable");
        }
        static::$_dirDefault = $dirDefault;

        static::$_initedTs = microtime(true);
    }

    static function write(string $logNameOrFile, $mixed, string $type = self::TYPE_EXTENDED)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $logFile = ($logNameOrFile[0] == '/') ? $logNameOrFile : static::$_dirDefault . '/' . $logNameOrFile . '.log';

        if (is_scalar($mixed)) {
            $mixed = [$mixed];
        }
        elseif (is_array($mixed)) {
            foreach ($mixed as $item) {
                if (!is_scalar($item)) {
                    throw new Err("Message contains not a scalar item: ", $item);
                }
            };
        }
        else {
            throw new Err("Bad message type: ", gettype($mixed));
        }

        $microTime = explode('.', microtime(true));
        $microSec = isset($microTime[1]) ? sprintf('%04d', $microTime[1]) : '0000';

        if ($type == self::TYPE_SIMPLE) {
            $formattedMsg = [
                strftime("%Y-%m-%d %H:%M:%S." . $microSec, $microTime[0]),
                join(self::VALUES_DELIMITER, $mixed)
            ];
        }
        elseif ($type == self::TYPE_EXTENDED) {
            $timePassed = sprintf('%.4f', microtime(true) - static::$_initedTs);
            $formattedMsg = [
                strftime("%Y-%m-%d %H:%M:%S." . $microSec, $microTime[0]) . " ($timePassed)",
                Request::url() ?: '%requestUrl%',
                Request::host() ?: '%host%',
                Request::ip() ?: '%ip%',
                Request::referrer() ?: '%referrer%',
                Request::browserStr() ?: '%browserStr%',
                join(self::VALUES_DELIMITER, $mixed)
            ];
        }
        else {
            throw new Err("Bad log type [$type]");
        }

        $formattedMsg = join(self::VALUES_DELIMITER, $formattedMsg);

        if (file_put_contents($logFile, $formattedMsg . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new Err("Failed to write [$formattedMsg] to file [$logFile]");
        }
    }

    static function writeError(string $logNameOrFile, string $errorMessage)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $logFile = ($logNameOrFile[0] == '/') ? $logNameOrFile : static::$_dirDefault . '/' . $logNameOrFile . '.log';

        $microTime = explode('.', microtime(true));
        $microSec = isset($microTime[1]) ? sprintf('%04d', $microTime[1]) : '0000';
        $timePassed = sprintf('%.4f', microtime(true) - static::$_initedTs);

        $formattedMsg =
            $errorMessage . PHP_EOL .
            strftime("%Y-%m-%d %H:%M:%S." . $microSec, $microTime[0]) . " ($timePassed)" . self::VALUES_DELIMITER .
            (Request::url() ?: '%requestUrl%') . self::VALUES_DELIMITER .
            (Request::host() ?: '%host%') . self::VALUES_DELIMITER .
            (Request::ip() ?: '%ip%') . self::VALUES_DELIMITER .
            (Request::referrer() ?: '%referrer%') . self::VALUES_DELIMITER .
            (Request::browserStr() ?: '%browserStr%') . self::VALUES_DELIMITER .
            PHP_EOL; // Extra line added for visual separation of error messages in a log file

        if (file_put_contents($logFile, $formattedMsg . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new Err("Failed to write [$formattedMsg] to file [$logFile]");
        }
    }
}
