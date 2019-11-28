<?php
/**
 *
 */
class ClassUserApiKey extends ClassAbstractApiKey
{
    static function encryptApiSecret(string $apiSecret, int $userId): string
    {
        if (!$apiSecret || !$userId) {
            throw new Err("Bad api secret data to encrypt");
        }

        $apiSecretEncB64 = CryptAsym::encrypt(
            $apiSecret,
            Config::get('user.apiKey.apiSecret.publicKey'),
            Config::get('user.apiKey.apiSecret.hmacKey'),
            CryptAsym::EXPIRE_NEVER,
            [$userId]
        );
        if (!$apiSecretEncB64) {
            throw new Err("Failed to encrypt api secret");
        }
        return $apiSecretEncB64;
    }

    static function decryptApiSecret(string $apiSecretEncB64, int $userId): string
    {
        if (!$apiSecretEncB64 || !$userId) {
            throw new Err("Bad api secret data to decrypt");
        }

        $apiSecret = CryptAsym::decrypt(
            $apiSecretEncB64,
            Config::get('user.apiKey.apiSecret.privateKey'),
            Config::get('user.apiKey.apiSecret.hmacKey'),
            [$userId]
        );
        if (!$apiSecret) {
            throw new Err("Failed to decrypt api secret");
        }
        return $apiSecret;
    }
}