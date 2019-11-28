<?php
/**
 *
 */
class Json
{
    static function encode($mixed, bool $exception = true, bool $prettyPrint = false): string
    {
        if ($prettyPrint) {
            $opts = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
        }
        else {
            $opts = JSON_UNESCAPED_UNICODE;
        }

        $res = json_encode($mixed, $opts);
        if (json_last_error() && $exception) {
            throw new Err("Json encode error: ", json_last_error_msg(), $mixed);
        }
        return $res;
    }

    static function decode(string $json, $exception = true)
    {
        $res = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() && $exception) {
            throw new Err("Json decode error: ", json_last_error_msg(), $json);
        }
        return $res;
    }

    static function isJson(string $json): bool
    {
        try {
            static::decode($json);
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }

    static function prettyFormat(string $json, bool $exception = true): string
    {
        $pretty = static::encode(static::decode($json, $exception), $exception, true);
        return $pretty;
    }
}