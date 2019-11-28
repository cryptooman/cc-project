<?php
/**
 * Usage:
 *      ErrHandler::init( ... );
 *      try {
 *          ...
 *      }
 *      catch(Exception $e) {
 *          ErrHandler::handle($e);
 *      }
 */
class ErrHandler
{
    const MSG_DEFAULT = 'Error occurred. Please try again later. Sorry for inconvenience.';

    protected static $_displayErr;
    protected static $_logErr;
    protected static $_logErrFile;
    protected static $_inited;

    static function init(bool $displayErr = false, bool $logErr = true, string $logErrFile = '/var/tmp/errors.log')
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        static::$_displayErr = $displayErr;
        static::$_logErr = $logErr;
        static::$_logErrFile = $logErrFile;
    }

    static function handle(Exception $e, bool $rollbackActiveTransaction = true)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init(). Error: ", __CLASS__, $e->getMessage()); }

        $msg = static::getFormattedErrMsg($e);

        static::display($msg);

        if (static::$_logErr) {
            Log::writeError(static::$_logErrFile, $msg);
        }

        if ($rollbackActiveTransaction) {
            Model::inst()->rollback();
        }
    }

    static function getFormattedErrMsg(Exception $e): string
    {
        return '[' . ($e->getCode() ?: Err::E_DEFAULT) . '] ' . $e->getMessage() . PHP_EOL .
            $e->getFile() . ':' . $e->getLine() . PHP_EOL .
            $e->getTraceAsString();
    }

    static function log(string $msg)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::display($msg);

        if (static::$_logErr) {
            Log::writeError(static::$_logErrFile, static::_attachBacktraceToErrMsg($msg));
        }
    }

    static function display(string $msg)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_displayErr) {
            return;
        }

        $msg = static::_attachBacktraceToErrMsg($msg);

        if (isset($_SERVER['HTTP_USER_AGENT']) && !stristr($_SERVER['HTTP_USER_AGENT'], 'curl')) {
            echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>' . PHP_EOL;
            echo '<pre>' . PHP_EOL;
            echo $msg . PHP_EOL;
            echo '</pre>' . PHP_EOL;
        }
        else {
            echo $msg . PHP_EOL;
        }

        if (ob_get_length()) {
            ob_flush();
        }
    }

    static function isDisplay(): bool
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::$_displayErr;
    }

    static function cutLen($mixed, int $len = 100): string
    {
        if (!is_scalar($mixed)) {
            $mixed = print_r($mixed, 1);
        }
        return Str::cutAddDots($mixed, $len);
    }

    protected static function _attachBacktraceToErrMsg($msg): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return $msg . PHP_EOL . Debug::getTraceAsString($depth = 10, $offset = 2);
    }
}