<?php
/**
 * Usage:
 *      ClassCliScript::run(
 *          basename(__FILE__), ['arg1', '[arg2]'], ['arg1'], function() {
 *              ... Script logic
 *          }
 *      );
 *
 * NOTE: Onchange sync with run.php
 */
class ClassCliScript
{
    static function run(string $scriptName, array $cliArgs, array $lockIdCliArgs, $runCallback)
    {
        // Init

        error_reporting(E_ALL);

        $timeStart = microtime(true);

        require_once dirname(__FILE__) . '/../autoload.php';

        set_error_handler(function(int $code, string $message, string $file, int $line, array $context) {
            throw new Err("$message at $file:$line", (new ErrCode($code)));
        }, E_ALL);

        define('DIR_ROOT', realpath(dirname(__FILE__) . '/..'));
        if (!DIR_ROOT) {
            throw new Err("Failed to set DIR_ROOT");
        }

        try {
            Config::init([
                [DIR_ROOT . '/configs/config.php', true],
                [DIR_ROOT . '/configs/configKeys.local.php', true],
                [DIR_ROOT . '/configs/config.local.php', false],
            ]);
            ini_set('display_errors', 'On');
            ini_set('log_errors', Config::get('error.log'));
            ini_set('error_log', Config::get('error.logFile'));
            ini_set('memory_limit', Config::get('cli.memoryLimit'));
            ini_set('max_execution_time', Config::get('cli.timeLimitSec'));

            $dirs = [
                Config::get('log.dir'),
                Config::get('cache.dir'),
                Config::get('cache.dirPermanent'),
                Config::get('tmp.dir'),
                Config::get('cli.lockFileDir'),
                Config::get('translate.cacheDir'),
            ];
            foreach (array_unique($dirs) as $dir) {
                if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
                    throw new Err("Failed to make dir [$dir]");
                }
                if (!is_writable($dir)) {
                    throw new Err("Not a writable dir [$dir]");
                }
            }

            ErrHandler::init(
                $display = true, Config::get('error.log'), Config::get('error.logFile')
            );
            Log::init(
                Config::get('log.dir')
            );
            Verbose::init(
                Verbose::LEVEL_1, $display = true, Config::get('verbose.log')
            );
            Request::init(
            );
            Cache::init(
                Config::get('cache.dir'), Config::get('cache.enabled')
            );
            View::init(
                Config::get('view.baseDir')
            );
            Mailer::init(
                Config::get('email.baseDir'), Config::get('email.sender.email'), Config::get('email.sender.name'),
                Config::get('email.recipients.sys'), Config::get('email.enabled')
            );
            CryptSym::init(
                Config::get('crypt.sym.cipherAlg')
            );
            CryptAsym::init(
                Config::get('crypt.asym')
            );
            Curl::init([
                CURLOPT_PORT => 443, CURLOPT_SSL_VERIFYPEER => 1, CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            Model::init(
                Config::get('mysql.host'), Config::get('admin.mysql.user'), Config::get('admin.mysql.pass'), Config::get('mysql.database')
            );
            Daemon::init(
                Config::get('cli.lockFileDir'), Daemon::LIFETIME_DAY
            );
            Language::init(
                Config::get('cli.lang'), Config::get('translate.sourceDir'), Config::get('translate.cacheDir')
            );
            Language::makeTranslateCache(null, Config::get('translate.resetCache'));

            // Check params

            if (array_sum(Config::get('mode')) != 1) {
                throw new Err("Bad mode: At least one and only one mode can be selected");
            }
            if (Config::get('mode.prod')) {
                if (!Config::get('user.mysql.pass') || !Config::get('admin.mysql.pass')) {
                    throw new Err("Prod mode error: Mysql password must be not empty");
                }
            }

            // Run

            $cliArgs = F::getCliArgs($cliArgs, true, $verboseLevel);
            if ($verboseLevel !== null) {
                Verbose::resetLevel($verboseLevel);
            }

            $lockIdArgs = [$scriptName];
            if ($lockIdCliArgs) {
                foreach ($lockIdCliArgs as $arg) {
                    if (empty($cliArgs[$arg])) {
                        throw new Err("Empty lock arg [$arg]");
                    }
                }
                $lockIdArgs = array_merge($lockIdArgs, $lockIdCliArgs);
            }

            list($lockFile, $processPid) = F::lockRunOnce($lockIdArgs, Config::get('cli.lockFileDir'));

            Verbose::echo1("Started script [$scriptName]: pid [$processPid] lock file [$lockFile]");
            Verbose::echo1("Mem peak usage (start): " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " Mb");

            $runCallback($cliArgs);

            Verbose::echo1(sprintf("Time taken: %.2f", microtime(true) - $timeStart));
            Verbose::echo1("Mem peak usage (end): " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " Mb");
            echo PHP_EOL;
        }
        catch (Exception $e) {
            ErrHandler::handle($e);
            exit(1);
        }
    }
}