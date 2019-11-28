<?php
/**
 * Various functions
 */
class F
{
    static function echo(string $str)
    {
        $microTime = explode('.', microtime(true));
        $microSec = isset($microTime[1]) ? sprintf('%04d', $microTime[1]) : '0000';
        $line = date('Y-m-d H:i:s.') . $microSec . "\t" . $str;

        if (isset($_SERVER['HTTP_USER_AGENT']) && !stristr($_SERVER['HTTP_USER_AGENT'], "curl")) {
            echo '<pre>' . $line . '</pre>' . PHP_EOL;
        }
        else {
            echo $line . PHP_EOL;
        }
    }

    // Like array_flip(), but keeps values as is
    static function val2key(array $data, string $keyname = ''): array
    {
        if (!$data) {
            return [];
        }
        if(!is_array($data)) {
            $data = [$data];
        }
        $res = [];
        foreach ($data as $_ => $val) {
            if ($keyname) {
                $res[$val[$keyname]] = $val;
            }
            else {
                $res[$val] = $val;
            }
        }
        return $res;
    }

    // http://www.mysite.com -> www.mysite.com
    // http://dev.en.mysite.com -> dev.en.mysite.com
    static function getDomainFromUrl(string $url): string
    {
        if (preg_match('!^(.+?://)?(www\.)?([^/\?&#;%@\s]+)!i', $url, $match)) {
            return $match[2] . $match[3];
        }
        return '';
    }

    static function formatUtime(int $utime, bool $time = true)
    {
        if ($time) {
            return strftime("%Y-%m-%d %H:%M:%S", (int) $utime);
        }
        return strftime("%Y-%m-%d", (int) $utime);
    }

    static function argsToTransliteratedStr(array $args, $delimiter = '__', $maxlen = 255)
    {
        if (!count($args)) {
            throw new Err("Empty args");
        }

        $result = [];
        foreach($args as $arg) {
            if (!is_scalar($arg)) {
                $arg = print_r($arg, true);
            }
            $result[] = Str::translit($arg, true);
        }
        $result = join($delimiter, $result);

        if (strlen($result) > $maxlen) {
            throw new Err("Transliterated string [%s] is longer max allowed length [$maxlen]", $result);
        }
        return $result;
    }

    static function lockRunOnce(array $lockIdArgs, string $lockFileDir = '/var/lock')
    {
        if (!$lockIdArgs) {
            throw new Err("Empty lock id args");
        }
        if (!is_dir($lockFileDir) || !is_writable($lockFileDir)) {
            throw new Err("Bad lock file directory [$lockFileDir]: Not exists or not writable");
        }

        if (ob_get_status()) {
            ob_end_flush();
        }

        $lockFileName = F::argsToTransliteratedStr($lockIdArgs, '__', 200) . '.lock';
        if (strlen($lockFileName) > Cnst::FILE_NAME_LENGTH_MAX) {
            throw new Err("Bad lock file name [$lockFileName]: Too long");
        }

        $lockFile = $lockFileDir . '/' . $lockFileName;
        if (is_file($lockFile)) {
            if (($runPid = file_get_contents($lockFile)) === false) {
                throw new Err("Failed to get PID from file [$lockFile]");
            }
            if ($runPid <= 0) {
                throw new Err("Bad PID [$runPid] from file [$lockFile]");
            }

            $running = false;
            try {
                $running = (bool) CmdBash::exec("/bin/ps -p $runPid -o comm,args=ARGS | /bin/grep php");
            }
            catch(Exception $e) {
                if ($e->getCode() != 1) {
                    throw new Err("Unexpected error code [%s] for command [%s]", $e->getCode(), CmdBash::getCmdFormatted());
                }
            }
            if ($running) {
                Verbose::echo1("Running instance already exists: pid [$runPid]");
                exit(1);
            }
        }

        $currPid = getmypid();
        if ($currPid <= 0) {
            throw new Err("Bad process PID [$currPid]");
        }
        if (!file_put_contents($lockFile, $currPid)) {
            throw new Err("Failed to create lock file [$lockFile] for process PID [$currPid]");
        }

        return [$lockFile, $currPid];
    }

    // Usage:
    //      $args = F:getCliArgs(['arg1', '[arg2]']);
    //              F:getCliArgs(['arg1=<arg-value-hint>', '[arg2=<arg-value-hint>]']);
    //                  "arg1" is mandatory
    //                  "arg2" is optional
    //
    //      $ php script.php --arg1=ARG1-VAL [-h] [-v|vv|vvv]
    //          -h  - Show usage help and exit
    static function getCliArgs(array $args, bool $exception = true, &$verboseLevel = null): array
    {
        global $argv;
        if (!$argv || !is_array($argv)) {
            throw new Err("Argv is not defined or not an array");
        }

        $scriptName = basename(array_shift($argv));

        // Prepare args opts
        $tmp = [];
        foreach ($args as $arg) {
            // Optional args
            if (preg_match('!^\[(.+)\]$!', $arg, $match)) {
                $arg = explode('=', $match[1]);
                $tmp[$arg[0]] = [
                    'required' => false,
                    'set' => false,
                    'hint' => (!empty($arg[1]) ? $arg[1] : ''),
                ];
            }
            // Mandatory args
            else {
                $arg = explode('=', $arg);
                $tmp[$arg[0]] = [
                    'required' => true,
                    'set' => false,
                    'hint' => (!empty($arg[1]) ? $arg[1] : ''),
                ];
            }
        }
        $args = $tmp;
        unset($tmp);

        // Set usage help
        $usageHelp = [$scriptName];
        foreach ($args as $name => $opt) {
            if (!$opt['required']) {
                $usageHelp[] = "[--$name" . ($opt['hint'] ? '=' . $opt['hint'] : '') . "]";
            }
            else {
                $usageHelp[] = "--$name" . ($opt['hint'] ? '=' . $opt['hint'] : '');
            }
        }
        $usageHelp[] = '-h';
        $usageHelp[] = '-v|vv|vvv';
        $usageHelp = join(' ', $usageHelp);

        // Get args from cli
        $result = [];
        foreach ($argv as $arg) {
            if (preg_match('!^--([^\s=]+)=(.*)$!', $arg, $match)) {
                $argName = $match[1];
                $argVal = trim($match[2]);
                if (!isset($args[$argName])) {
                    throw new Err("Unknown arg [$argName]: Usage: $usageHelp");
                }
                $args[$argName]['set'] = true;
                $result[$argName] = trim($argVal);
            }
            elseif (preg_match('!^-h$!', $arg)) {
                echo $usageHelp . PHP_EOL;
                exit(0);
            }
            elseif (preg_match('!^-(v{1,3})$!', $arg, $match)) {
                $verboseLevel = count(preg_split('!!', $match[1], -1, PREG_SPLIT_NO_EMPTY));
            }
            else {
                throw new Err("Unknown arg [$arg]: Usage: $usageHelp");
            }
        }

        // Check args opts
        foreach ($args as $argName => $argOpts) {
            if (!$argOpts['set']) {
                if ($argOpts['required']) {
                    throw new Err("Arg [$argName] is required: Usage: $usageHelp");
                }
                $result[$argName] = null;
            }
        }

        return $result;
    }

}