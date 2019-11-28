<?php
/**
 *
 */
class HashHmac
{
    const SHA256 = 'sha256';
    const SHA256_HASH_HEX_LEN = 64;
    const SHA256_HASH_RAW_LEN = 32;

    const SHA384 = 'sha384';
    const SHA384_HASH_HEX_LEN = 96;
    const SHA384_HASH_RAW_LEN = 48;

    const SHA512 = 'sha512';
    const SHA512_HASH_HEX_LEN = 128;
    const SHA512_HASH_RAW_LEN = 64;

    static function sha256(string $data, string $key, bool $rawOutput = false): string
    {
        return static::_hash(
            self::SHA256, $data, $key, ($rawOutput ? self::SHA256_HASH_RAW_LEN : self::SHA256_HASH_HEX_LEN), $rawOutput
        );
    }

    static function sha384(string $data, string $key, bool $rawOutput = false): string
    {
        return static::_hash(
            self::SHA384, $data, $key, ($rawOutput ? self::SHA384_HASH_RAW_LEN : self::SHA384_HASH_HEX_LEN), $rawOutput
        );
    }

    static function sha512(string $data, string $key, bool $rawOutput = false): string
    {
        return static::_hash(
            self::SHA512, $data, $key, ($rawOutput ? self::SHA512_HASH_RAW_LEN : self::SHA512_HASH_HEX_LEN), $rawOutput
        );
    }

    protected static function _hash(string $alg, string $data, string $key, int $expectedHashLen, bool $rawOutput)
    {
        if (!$key) {
            throw new Err("Key is empty or equal to zero");
        }

        $hash = hash_hmac($alg, $data, $key, $rawOutput);
        $hash = strtolower($hash);

        if (!$hash || strlen($hash) != $expectedHashLen) {
            throw new Err("Failed to hash hmac [$alg] data");
        }
        return $hash;
    }
}