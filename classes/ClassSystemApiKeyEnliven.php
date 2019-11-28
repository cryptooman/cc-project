<?php
/**
 *
 */
class ClassSystemApiKeyEnliven extends ClassAbstractApiKeyEnliven
{
    function __construct(int $apiKeyId)
    {
        parent::__construct($apiKeyId, ModelSystemApiKeys::inst());

        if (!empty($this->_apiKey['userId'])) {
            throw new Err("Bad api key [$this->_apiKeyEntity]: ", ClassAbstractApiKey::formatVerbose($this->_apiKey));
        }
    }
}