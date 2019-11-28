<?php
/**
 * Usage:
 *      ...
 */
class Debug
{
    const VALUES_DELIMITER = "\t";

    static function getCaller(bool $addLocation = false): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
        if (!$backtrace) {
            return '';
        }

        $caller = [];
        if (!empty($backtrace[2])) {
            $caller = $backtrace[2];
        }
        elseif (!empty($backtrace[1])) {
            $caller = $backtrace[1];
        }

        if (!$caller) {
            return '';
        }

        $location = '';
        if (!empty($caller['file']) && !empty($caller['line'])) {
            $location = $caller['file'] . ':' . $caller['line'];
        }

        if (!empty($caller['class'])) {
            return $caller['class'] . "::" . $caller['function'] . ($addLocation ? self::VALUES_DELIMITER . $location : '');
        }
        elseif (!empty($caller['function'])) {
            return $caller['function'] . ($addLocation ? self::VALUES_DELIMITER . $location : '');
        }
        elseif ($location) {
            return $location;
        }
        throw new Err("Bad caller format: ", $caller);
    }

    static function getTraceAsString(int $depth = 5, int $offset = 0)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $offset + $depth);
        if (!$backtrace) {
            return '';
        }

        $backtrace = array_slice($backtrace, $offset, $depth);
        if (!$backtrace) {
            return '';
        }

        $res = [];
        foreach ($backtrace as $i => $bt) {
            $res[] = '#' . $i . ' ' . $bt['file'] . ':' . $bt['line'];
        }
        $res = join(PHP_EOL, $res);
        return $res;
    }

    static function pr($mixed, bool $exit = true, bool $ob_clean = false, int $max_len = 1000000)
    {
        if ($ob_clean) {
            @ob_clean();
        }

        if (is_bool($mixed)) {
            $mixed = $mixed ? '[true]' : '[false]';
        }
        else if (is_null($mixed)) {
            $mixed = '[null]';
        }

        $mixed = print_r($mixed, 1);
        if (mb_strlen($mixed) > $max_len) {
            $mixed = mb_substr($mixed, 0, $max_len)."\n... Other data trimmed";
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) && !stristr($_SERVER['HTTP_USER_AGENT'], "curl")) {
            echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><pre>' . $mixed . '</pre>' . PHP_EOL;
        }
        else {
            echo $mixed . PHP_EOL;
        }

        if ($exit) {
            exit(1);
        }
    }

    static function prv($mixed, $exit = true, $ob_clean = false, $max_len = 1000000)
    {
        if ($ob_clean) {
            @ob_clean();
        }

        ob_start();
        var_dump($mixed);
        $mixed = ob_get_clean();

        if (mb_strlen($mixed) > $max_len) {
            $mixed = mb_substr($mixed, 0, $max_len)."\n... Other data trimmed";
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) && !stristr($_SERVER['HTTP_USER_AGENT'], 'curl')) {
            echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><pre>' . $mixed . '</pre>' . PHP_EOL;
        }
        else {
            echo $mixed . PHP_EOL;
        }

        if ($exit) {
            exit(1);
        }
    }
}