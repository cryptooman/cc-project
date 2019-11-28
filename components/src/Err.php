<?php
/**
 * Usage:
 *      throw new Err("Unable to divide 10 / 0");
 *      throw new Err("Unable to divide 10 / 0, because: ", "Invalid math operation");
 *      throw new Err("Unable to divide %s / %s", 10, 0);
 *      throw new Err("Unable to divide %s", [10, 0]);
 *      throw new Err("Unable to divide %s / %s", 10, 0, (new ErrCode(1000)));
 */
class Err extends Exception
{
    const ARG_MAX_LEN = 2048;

    const E_DEFAULT = 1;

    function __construct(string $msg, ...$args)
    {
        $code = self::E_DEFAULT;

        $msgArgs = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $msgArgs[] = Str::cutAddDots((string) print_r($arg, true), self::ARG_MAX_LEN);
            }
            elseif (is_object($arg)) {
                if (get_class($arg) == 'ErrCode') {
                    $code = $arg->getCode();
                }
                else {
                    $msgArgs[] = Str::cutAddDots((string) print_r($arg, true), self::ARG_MAX_LEN);
                }
            }
            else {
                $msgArgs[] = Str::cutAddDots((string) $arg, self::ARG_MAX_LEN);
            }
        }

        $sCount = (int) substr_count($msg, '%s');
        if ($sCount) {
            $sArgs = array_slice($msgArgs, 0, $sCount);
            $msg = vsprintf($msg, $sArgs);
        }

        $jArgs = array_slice($msgArgs, $sCount);
        $msg .= join(', ', $jArgs);

        parent::__construct($msg, $code);
    }
}