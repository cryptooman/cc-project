<?php
/**
 *
 */
abstract class ModelAbstractAuthsAttempts extends Model
{
    function countActiveAttemptsByHash(string $hash): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) 
            FROM $this->_table 
            WHERE hash = :hash
                  AND validTill >= NOW() 
                  AND enabled = 1",
            ['hash' => $hash]
        )->value();
    }

    function countActiveAttemptsByIp(string $ip): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) 
            FROM $this->_table 
            WHERE ip = :ip
                  AND validTill >= NOW() 
                  AND enabled = 1",
            ['ip' => $ip]
        )->value();
    }

    function countActiveAttemptsTotal(): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) 
            FROM $this->_table 
            WHERE validTill >= NOW() 
                  AND enabled = 1"
        )->value();
    }

    function disableByHash(string $hash)
    {
        $this->query(
            "UPDATE $this->_table 
            SET enabled = 0 
            WHERE hash = :hash AND enabled = 1",
            ['hash' => $hash]
        )->exec();
    }

    protected function _insert(array $data, int $attemptRowExpireMinutes): int
    {
        return $this->query(
            "INSERT INTO $this->_table 
            SET %set%, 
                validTill = ADDDATE(NOW(), INTERVAL $attemptRowExpireMinutes MINUTE),
                created = NOW()",
            ['%set%' => $this->filter($data)]
        )->exec()->lastId();
    }
}