<?php
/**
 *
 */
abstract class ClassAbstractApiKey
{
    static function formatVerbose(array $apiKey): array
    {
        if (!$apiKey) {
            return [];
        }
        if (isset($apiKey['secretEncrypted'])) {
            $apiKey['secretEncrypted'] = Str::cutAddDots($apiKey['secretEncrypted'], 10);
        }
        return $apiKey;
    }
}