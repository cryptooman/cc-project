<?php
/**
 * Usage:
 *      cron
 *          * * * * * /usr/bin/php <php-daemon-cron-script> >> /var/log/<log-name> 2>&1
 *
 *      Daemon::init( ... );
 *      Daemon::run(function() {
 *          ... Iteration logic
 *      });
 *
 *      Shutdown
 *          ps axu | grep <process>
 *          kill -15 <process-pid>
 */
class Daemon
{
    const LIFETIME_HOUR = 3600;
    const LIFETIME_DAY = 86400;
    const LIFETIME_MONTH = 86400 * 30;

    const ITERATION_DELAY_SECOND = 1;
    const ITERATION_DELAY_MINUTE = 60;

    protected static $_lockFileDir;
    protected static $_lifetimeSecDefault;
    protected static $_iterationDelaySecDefault;
    protected static $_inited;

    static function init(
        string $lockFileDir = '/var/lock', int $lifetimeSecDefault = self::LIFETIME_DAY, int $iterationDelaySecDefault = self::ITERATION_DELAY_SECOND
    )
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        if (!is_dir($lockFileDir) || !is_writable($lockFileDir)) {
            throw new Err("Bad lock file directory [$lockFileDir]: Not exists or not writable");
        }
        static::$_lockFileDir = $lockFileDir;

        static::$_lifetimeSecDefault = $lifetimeSecDefault;

        if ($iterationDelaySecDefault <= 0) {
            throw new Err("Bad iteration delay seconds: Must be >= 1");
        }
        static::$_iterationDelaySecDefault = $iterationDelaySecDefault;
    }

    static function run($iterationCallback, array $lockIdArgs = [], int $lifetimeSec = null, int $iterationDelaySec = null)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!$iterationCallback) {
            throw new Err("Empty iteration callback");
        }
        if ($lifetimeSec === null) {
            $lifetimeSec = static::$_lifetimeSecDefault;
        }

        if ($iterationDelaySec === null) {
            $iterationDelaySec = static::$_iterationDelaySecDefault;
        }
        elseif ($iterationDelaySec <= 0) {
            throw new Err("Bad iteration delay seconds: Must be >= 1");
        }

        if ($lockIdArgs) {
            list($lockFile, $processPid) = F::lockRunOnce($lockIdArgs, static::$_lockFileDir);
            Verbose::echo1("Daemon started: pid [$processPid] lock file [$lockFile]");
        }
        else {
            Verbose::echo1("Daemon started: pid [" . getmypid() . "] lock file [--]");
        }
        Verbose::echo1("Memory peak usage (start): " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " Mb");

        pcntl_signal(SIGTERM, function($signal) {
            if ($signal == SIGTERM) {
                echo PHP_EOL;
                Verbose::echo1("***** Termination signal received *****");
                exit(0);
            }
        });

        $startedTs = time();
        $elapsedTime = 0;
        $iterations = 0;

        $prevIterationEndTs = 0;
        while(1) {
            $elapsedTime = time() - $startedTs;
            if($elapsedTime >= $lifetimeSec) {
                break;
            }

            Verbose::echo2(Verbose::EMPTY_LINE);
            Verbose::echo2(Verbose::EMPTY_LINE);
            Verbose::echo2("Iteration: $iterations");
            Verbose::echo2(
                "Time taken: iteration [%s] total [%s] sec",
                ($prevIterationEndTs ? sprintf('%0.4f', microtime(1) - $prevIterationEndTs) : '0.0000'), $elapsedTime
            );
            Verbose::echo2("Memory peak usage: ", round(memory_get_peak_usage(true) / 1024 / 1024) . " Mb");
            Verbose::echo2(Verbose::EMPTY_LINE);
            Verbose::echo2(Verbose::EMPTY_LINE);

            $iterationCallback();

            $iterations++;
            $prevIterationEndTs = microtime(1);

            pcntl_signal_dispatch();

            sleep($iterationDelaySec);
        }

        Verbose::echo1("Daemon lifetime expired: Elapsed time sec [$elapsedTime] iterations [$iterations]");
        Verbose::echo1("Memory peak usage (end): " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " Mb");
    }
}