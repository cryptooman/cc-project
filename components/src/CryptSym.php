<?php
/**
 * Usage:
 *      CryptSym::init( ... );
 *      CryptSym::encrypt( ... );
 *      CryptSym::decrypt( ... );
 *
 * NOTE: In CBC mode it is strongly advised to change cipher key once per 2^32 text blocks
 *       In CTR mode: 2^64 blocks
 */
class CryptSym
{
    const CIPHER_ALG_AES_256_CBC = 'aes-256-cbc'; // Rijndael
    const CIPHER_ALG_AES_256_CTR = 'aes-256-ctr';
    const CIPHER_ALG_BF_CBC = 'bf-cbc'; // Blowfish

    const CIPHER_KEY_SIZE_MIN = 8; // I.e. pass-phrase min length
    const HMAC_KEY_SIZE_MIN = 8;

    const DECRYPT_RND_SLEEP = false; // To complicate timing attack

    const EXPIRE_MINUTE = 60;
    const EXPIRE_HOUR = 3600;
    const EXPIRE_DAY = 86400;
    const EXPIRE_NEVER = 0;

    protected static $_cipherAlgDefault;
    protected static $_inited;

    static function init(string $cipherAlgDefault = self::CIPHER_ALG_AES_256_CTR)
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        if (!$cipherAlgDefault) {
            throw new Err("Cipher algorithm is empty");
        }
        static::$_cipherAlgDefault = $cipherAlgDefault;
    }

    static function encrypt(
        string $data, string $cipherKeyB64, string $hmacKeyB64, int $expireSec = self::EXPIRE_NEVER, array $hmacExtraData = [], string $cipherAlg = null
    ): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!strlen($data)) {
            throw new Err("Bad data to encrypt");
        }

        $cipherKey = Base64::decode($cipherKeyB64);
        if (strlen($cipherKey) < self::CIPHER_KEY_SIZE_MIN) {
            throw new Err("Bad cipher key: Size must be >= %s", self::CIPHER_KEY_SIZE_MIN);
        }

        $hmacKey = Base64::decode($hmacKeyB64);
        if (strlen($hmacKey) < self::HMAC_KEY_SIZE_MIN) {
            throw new Err("Bad hmac key: Size must be >= %s", self::HMAC_KEY_SIZE_MIN);
        }

        if ($cipherKey == $hmacKey) {
            throw new Err("Cipher key equal to hmac key");
        }

        if ($expireSec < 0) {
            throw new Err("Bad expire: Must be >= 0");
        }

        if (!$cipherAlg) {
            $cipherAlg = static::$_cipherAlgDefault;
        }

        $ivLen = openssl_cipher_iv_length($cipherAlg);
        if (!$ivLen) {
            throw new Err("Empty iv length");
        }

        $iv = openssl_random_pseudo_bytes($ivLen, $cryptoStrong);
        if (!$iv || !$cryptoStrong) {
            throw new Err("Bad iv: Empty or not crypt-strong");
        }

        $validTill = ($expireSec != self::EXPIRE_NEVER) ? gmdate('U') + $expireSec : 0;
        $validTill = sprintf('%010d', $validTill);
        if (strlen($validTill) != 10) {
            throw new Err("Bad valid till format");
        }

        $hmac = HashHmac::sha256($validTill . join(' ', $hmacExtraData) . $data, $hmacKey, true);
        if (!$hmac) {
            throw new Err("Failed to hash hmac data");
        }

        $encDataRaw = openssl_encrypt($hmac . $validTill . $data, $cipherAlg, $cipherKey, OPENSSL_RAW_DATA, $iv);
        if (!$encDataRaw) {
            throw new Err("Failed to encrypt data");
        }

        return Base64::encode($iv . $encDataRaw);
    }

    static function decrypt(
        string $encDataB64, string $cipherKeyB64, string $hmacKeyB64, array $hmacExtraData = [], string $cipherAlg = null
    ): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (self::DECRYPT_RND_SLEEP) {
            usleep(random_int(1, 400));
        }

        $encDataRaw = Base64::decode($encDataB64);

        $cipherKey = Base64::decode($cipherKeyB64);
        if (strlen($cipherKey) < self::CIPHER_KEY_SIZE_MIN) {
            throw new Err("Bad cipher key: Size must be >= %s", self::CIPHER_KEY_SIZE_MIN);
        }

        $hmacKey = Base64::decode($hmacKeyB64);
        if (strlen($hmacKey) < self::HMAC_KEY_SIZE_MIN) {
            throw new Err("Bad hmac key: Size must be >= %s", self::HMAC_KEY_SIZE_MIN);
        }

        if ($cipherKey == $hmacKey) {
            throw new Err("Cipher key equal to hmac key");
        }

        if (!$cipherAlg) {
            $cipherAlg = static::$_cipherAlgDefault;
        }

        $ivLen = openssl_cipher_iv_length($cipherAlg);
        if (!$ivLen) {
            throw new Err("Empty iv length");
        }

        $iv = substr($encDataRaw, 0, $ivLen);
        if (!$iv) {
            throw new Err("Failed to get iv");
        }

        $encDataRaw = substr($encDataRaw, $ivLen);
        if (!$encDataRaw) {
            throw new Err("Failed to get encrypted data");
        }

        $data = openssl_decrypt($encDataRaw, $cipherAlg, $cipherKey, $options = OPENSSL_RAW_DATA, $iv);
        if (!$data) {
            throw new Err("Failed to decrypt data");
        }

        $hmacRawLen = HashHmac::SHA256_HASH_RAW_LEN;
        $hmac = substr($data, 0, $hmacRawLen);
        if (!$hmac) {
            throw new Err("Failed to get hmac");
        }

        $validTillLen = 10;
        $validTill = substr($data, $hmacRawLen, $validTillLen);
        if (!$validTill) {
            throw new Err("Failed to get valid till");
        }

        $data = substr($data, $hmacRawLen + $validTillLen);
        if ($data === false) {
            throw new Err("Failed to get data");
        }

        $hmacCalc = HashHmac::sha256($validTill . join(' ', $hmacExtraData) . $data, $hmacKey, true);
        if (!hash_equals($hmac, $hmacCalc)) {
            throw new Err("Bad hmac");
        }

        if (((int) $validTill) && gmdate('U') > (int) $validTill) {
            throw new Err("Data is expired");
        }

        return $data;
    }

    static function generateKeys(int $cipherKeySize = 32, int $hmacKeySize = 32): array
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $generate = function(int $size, int $sizeMin): string {
            if ($size < $sizeMin) {
                throw new Err("Bad key size: Must be >= $sizeMin");
            }

            $keyRaw = openssl_random_pseudo_bytes($size, $cryptoStrong);
            if (!$keyRaw || !$cryptoStrong) {
                throw new Err("Failed to generate key");
            }

            return Base64::encode($keyRaw);
        };
        return [
            'cipher' => $generate($cipherKeySize, self::CIPHER_KEY_SIZE_MIN),
            'hmac' => $generate($hmacKeySize, self::HMAC_KEY_SIZE_MIN)
        ];
    }
}
