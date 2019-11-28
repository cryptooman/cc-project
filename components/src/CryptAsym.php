<?php
/**
 * Usage:
 *      CryptAsym::init( ... );
 *      CryptAsym::encrypt( ... );
 *      CryptAsym::decrypt( ... );
 */
class CryptAsym
{
    const DIGEST_ALG_SHA256 = 'sha256';
    const DIGEST_ALG_SHA512 = 'sha512';

    const PRIVATE_KEY_BITS_2048 = 2048;
    const PRIVATE_KEY_BITS_4096 = 4096;

    const PRIVATE_KEY_BITS_MIN = self::PRIVATE_KEY_BITS_2048;

    const PRIVATE_KEY_TYPE_RSA = OPENSSL_KEYTYPE_RSA;

    const HMAC_KEY_SIZE_MIN = 8;

    const DECRYPT_RND_SLEEP = false; // To complicate timing attack

    const EXPIRE_MINUTE = 60;
    const EXPIRE_HOUR = 3600;
    const EXPIRE_DAY = 86400;
    const EXPIRE_NEVER = 0;

    protected static $_configDefault;
    protected static $_inited;

    static function init(array $config = [
        'digest_alg' => self::DIGEST_ALG_SHA256,
        'private_key_bits' => self::PRIVATE_KEY_BITS_2048,
        'private_key_type' => self::PRIVATE_KEY_TYPE_RSA,
    ])
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        static::_checkConfig($config);
        static::$_configDefault = $config;
    }

    static function encrypt(
        string $data, string $publicKey, string $hmacKeyB64, int $expireSec = self::EXPIRE_NEVER, array $hmacExtraData = []
    ): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!strlen($data)) {
            throw new Err("Bad data to encrypt");
        }

        if (!$publicKey) {
            throw new Err("Empty public key");
        }

        $hmacKey = Base64::decode($hmacKeyB64);
        if (strlen($hmacKey) < self::HMAC_KEY_SIZE_MIN) {
            throw new Err("Bad hmac key: Size must be >= %s", self::HMAC_KEY_SIZE_MIN);
        }

        if ($expireSec < 0) {
            throw new Err("Bad expire: Must be >= 0");
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

        $encDataRaw = '';
        openssl_public_encrypt($hmac . $validTill . $data, $encDataRaw, $publicKey, OPENSSL_PKCS1_PADDING);
        if (!$encDataRaw) {
            throw new Err("Failed to encrypt data");
        }

        return Base64::encode($encDataRaw);
    }

    static function decrypt(string $encDataB64, string $privateKey, string $hmacKeyB64, array $hmacExtraData = []): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (self::DECRYPT_RND_SLEEP) {
            usleep(random_int(1, 12000));
        }

        $encDataRaw = Base64::decode($encDataB64);

        if (!$privateKey) {
            throw new Err("Empty private key");
        }

        $hmacKey = Base64::decode($hmacKeyB64);
        if (strlen($hmacKey) < self::HMAC_KEY_SIZE_MIN) {
            throw new Err("Bad hmac key: Size must be >= %s", self::HMAC_KEY_SIZE_MIN);
        }

        $data = '';
        openssl_private_decrypt($encDataRaw, $data, $privateKey, OPENSSL_PKCS1_PADDING);
        if (!$data) {
            throw new Err("Failed to decrypt data");
        }
        unset($privateKey);

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

    static function generateKeys(int $hmacKeySize = 32, array $config = null): array
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($config) {
            static::_checkConfig($config);
        }
        else {
            $config = static::$_configDefault;
        }

        $keyResource = openssl_pkey_new($config);
        if (!$keyResource) {
            throw new Err("Failed to make key resource");
        }

        $privateKey = '';
        $res = openssl_pkey_export($keyResource, $privateKey, null, $config);
        if (!$res || !$privateKey) {
            throw new Err("Failed to export private key into PEM format");
        }

        $keyDetails = openssl_pkey_get_details($keyResource);
        if (!$keyDetails || !$keyDetails['key']) {
            throw new Err("Failed to get key details");
        }
        $publicKey = $keyDetails['key'];

        $hmacKey = CryptSym::generateKeys(CryptSym::CIPHER_KEY_SIZE_MIN, $hmacKeySize)['hmac'];

        return ['private' => $privateKey, 'public' => $publicKey, 'hmac' => $hmacKey];
    }

    protected static function _checkConfig(array $config)
    {
        if (empty($config['digest_alg'])) {
            throw new Err("Bad config: Empty digest_alg");
        }
        if (empty($config['private_key_bits']) || $config['private_key_bits'] < self::PRIVATE_KEY_BITS_MIN) {
            throw new Err("Bad config: private_key_bits is empty or less [%s]", self::PRIVATE_KEY_BITS_MIN);
        }
        if (!isset($config['private_key_type'])) {
            throw new Err("Bad config: Empty private_key_type");
        }
    }
}
