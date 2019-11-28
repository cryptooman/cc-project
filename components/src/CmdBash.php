<?php
/**
 * Usage:
 *      ...
 */
class CmdBash
{
    const E_CMD_NOT_FOUND   = 127;
    const E_TIMEOUT         = 137;
    const E_OUT_OF_MEMORY   = 255;

    protected static $_cmdFormatted;
    protected static $_errorCode = 0;
    protected static $_errorMsg = '';

    static function exec(
        string $cmd, bool $includeErr = true, int $timeoutSec = 0, int $memLimitMb = 0, bool $nonBlock = false, bool $exception = true
    ): string
    {
        $cmdFormatted = $cmd;

        if ($includeErr !== null) {
            if($includeErr) {
                $cmdFormatted .= ' 2>&1';
            }
            else {
                $cmdFormatted .= ' 2>/dev/null';
            }
        }

        if ($timeoutSec) {
            $cmdFormatted = "timeout --signal=9 $timeoutSec $cmdFormatted";
        }

        if ($memLimitMb) {
            $memLimitKb = $memLimitMb * 1024;
        }
        else {
            $phpMemLimit = ini_get('memory_limit');
            if (!$phpMemLimit) {
                throw new Err("Failed to get php value [memory_limit]");
            }

            $memLimitKb = 0;
            if (stristr($phpMemLimit, 'k')) {
                $memLimitKb = (int) $phpMemLimit;
            }
            elseif (stristr($phpMemLimit, 'm')) {
                $memLimitKb = ((int) $phpMemLimit) * 1024;
            }
            elseif (stristr($phpMemLimit, 'g')) {
                $memLimitKb = ((int) $phpMemLimit) * 1024 * 1024;
            }
            else {
                $memLimitKb = round(((int) $phpMemLimit) / 1024);
            }

            if ($memLimitKb <= 0) {
                throw new Err("Bad php [memory_limit] value [$phpMemLimit]");
            }
        }
        $cmdFormatted = "ulimit -d $memLimitKb && $cmdFormatted";

        if ($nonBlock) {
            $cmdFormatted = str_replace("'", "'\\''", $cmdFormatted); // Escape single quotes
            $cmdFormatted = "nohup sh -c '$cmdFormatted' > /dev/null &";
        }

        $cmdFormatted = str_replace("!", "\\!", $cmdFormatted); // Escape !
        static::$_cmdFormatted = $cmdFormatted;

        Verbose::echo2(str_replace('%', '%%', $cmdFormatted), []);

        exec($cmdFormatted, $output, $exitCode);

        $output = trim(join("\n", $output));

        if ($exitCode) {
            static::$_errorCode = $exitCode;
            static::$_errorMsg = $output;
            if ($exception) {
                throw new Err(static::getErrorMsg(), (new ErrCode(static::getErrorCode())) );
            }
        }

        return $output;
    }

    static function getCmdFormatted(): string
    {
        return static::$_cmdFormatted;
    }

    static function getErrorCode(): int
    {
        return static::$_errorCode;
    }

    static function getErrorMsg(): string
    {
        if (!static::$_errorMsg) {
            return '';
        }
        return "Failed to exec [" . static::$_cmdFormatted . "] with error [" . static::$_errorMsg . "]";
    }
}
