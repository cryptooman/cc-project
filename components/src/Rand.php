<?php
/**
 *
 */
class Rand
{
    static function int(int $min = 0, int $max = PHP_INT_MAX): string
    {
        return random_int($min, $max);
    }

    static function hash(int $len = 32): string
    {
        $hex = bin2hex(random_bytes(ceil($len / 2)));
        return substr($hex, 0, $len);
    }

    // Returns URL-safe random string of symbols a-zA-Z\d
    static function base62(int $len = 32): string
    {
        $bytes = random_bytes($len * 2);
        $str = Base64::encode($bytes);
        $str = preg_replace('![^a-zA-Z\d]!', '',  $str);
        return substr($str, 0, $len);
    }

    static function bytes(int $len = 32): string
    {
        return random_bytes($len);
    }
}