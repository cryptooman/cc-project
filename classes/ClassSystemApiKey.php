<?php
/**
 *
 */
class ClassSystemApiKey extends ClassAbstractApiKey
{
    static function encryptApiSecret(string $apiSecret): string
    {
        if (!$apiSecret) {
            throw new Err("Empty api secret to encrypt");
        }

        $apiSecretEncB64 = CryptAsym::encrypt(
            $apiSecret, Config::get('admin.apiKey.apiSecret.publicKey'), Config::get('admin.apiKey.apiSecret.hmacKey')
        );
        if (!$apiSecretEncB64) {
            throw new Err("Failed to encrypt api secret");
        }
        return $apiSecretEncB64;
    }

    static function decryptApiSecret(string $apiSecretEncB64): string
    {
        if (!$apiSecretEncB64) {
            throw new Err("Empty api secret to decrypt");
        }

        $apiSecret = CryptAsym::decrypt(
            $apiSecretEncB64, Config::get('admin.apiKey.apiSecret.privateKey'), Config::get('admin.apiKey.apiSecret.hmacKey')
        );
        if (!$apiSecret) {
            throw new Err("Failed to decrypt api secret");
        }
        return $apiSecret;
    }
}