<?php
/**
 *
 */
class Base64
{
    static function encode(string $data): string
    {
        if (!$data) {
            throw new Err("Input data is empty or equal to zero");
        }

        $encData = base64_encode($data);
        if (!$encData) {
            throw new Err("Failed to base64 encode data");
        }
        return $encData;
    }

    static function decode(string $encData): string
    {
        if (!$encData) {
            throw new Err("Input data is empty");
        }

        $data = base64_decode($encData, true);
        // NOTE: Does not expect empty string or zero to be encoded
        if (!$data) {
            throw new Err("Failed to base64 decode data");
        }
        return $data;
    }
}