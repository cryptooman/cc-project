<?php
/**
 * Usage:
 *      ...
 */
class Password
{
    protected static $_symbols = [
        33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 58,
        59, 60, 61, 62, 63, 64, 91, 93, 94, 95, 96, 123, 125, 126,
    ];

    static function hash(string $pass, string $salt, string $key): string
    {
        if (!$pass) {
            throw new Err("Password can't be empty or equal to zero");
        }

        $salt = trim($salt);
        if (!$salt) {
            throw new Err("Salt can't be empty or equal to zero");
        }

        $key = trim($key);
        if (!$key) {
            throw new Err("Key can't be empty or equal to zero");
        }

        $hash = HashHmac::sha256($pass, $salt . $key);
        if (!$hash) {
            throw new Err("Failed to hash password");
        }

        return $hash;
    }

    // NOTE: Do not use it for mass passwords generation as it possibly can produce predictable results
    static function generate(int $len = 15): string
    {
        $contents = [
            'A', 'A', 'A', 'A', 'A',    // Upper case: A-Z
            'a', 'a', 'a', 'a', 'a',    // Lower case: a-z
            'n', 'n', 'n',              // Number: 0-9
            's', 's', 's', 's',         // Symbols: !+()*-_...etc.
        ];
        $symbols = static::$_symbols;

        $cMerge = $contents;
        while(1) {
            if (count($contents) >= $len) {
                break;
            }
            $contents = array_merge($contents, $cMerge);
        }
        shuffle($contents);
        $contents = array_slice($contents, 0, $len);

        $pass = '';
        for ($i = 0; $i < count($contents); $i++) {
            $s = 0;
            switch ($contents[$i]) {
                case 'A':
                    $s = random_int(65, 90);
                    break;
                case 'a':
                    $s = random_int(97, 122);
                    break;
                case 'n':
                    $s = random_int(48, 57);
                    break;
                case 's':
                    $s = random_int(0, count($symbols) - 1);
                    $s = $symbols[$s];
                    break;
            }
            $pass .= chr($s);
        }
        if (!$pass) {
            throw new Err("Failed to generate password");
        }
        return $pass;
    }

    static function validateComplexity(string $pass, int $minLen = 15, $exception = true)
    {
        try {
            if (strlen($pass) < $minLen) {
                throw new Err("Bad password: Min length must be >= $minLen");
            }
            if (!preg_match('![a-z]!', $pass)) {
                throw new Err("Bad password: Must be at least one a-z letter");
            }
            if (!preg_match('![A-Z]!', $pass)) {
                throw new Err("Bad password: Must be at least one A-Z letter");
            }
            if (!preg_match('![0-9]!', $pass)) {
                throw new Err("Bad password: Must be at least one digit");
            }
            $symbols = array_map('chr', static::$_symbols);
            $symbols = array_map('preg_quote', $symbols);
            if (!preg_match('![' . join('', $symbols) . ']!', $pass)) {
                throw new Err("Bad password: Must be at least one non-letter-digit symbol");
            }
            return true;
        }
        catch (Exception $e) {
            if ($exception) {
                throw $e;
            }
            return false;
        }
    }
}
