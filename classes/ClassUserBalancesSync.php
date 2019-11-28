<?php
/**
 *
 */
class ClassUserBalancesSync extends ClassAbstractBalancesSync
{
    function __construct(int $apiKeyId)
    {
        parent::__construct($apiKeyId, ModelUsersApiKeys::inst(), ModelUsersBalances::inst());

        if (empty($this->_apiKey['userId'])) {
            throw new Err("Bad api key [$this->_apiKeyEntity]: ", ClassAbstractApiKey::formatVerbose($this->_apiKey));
        }

        $user = ModelUsers::inst()->getActiveUserById($this->_apiKey['userId'], ['id']);
        if (!$user) {
            throw new Err(
                "No active user [%s] for api key [$this->_apiKeyEntity:%s]",
                $this->_apiKey['userId'], $this->_apiKey['id'], (new ErrCode(ClassCronBalancesSyncWithExchanges::E_INACTIVE_USER))
            );
        }
        Verbose::echo1("Api key user id: ", $user['id']);
    }
}