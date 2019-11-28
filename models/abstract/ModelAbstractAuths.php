<?php
/**
 *
 */
abstract class ModelAbstractAuths extends Model
{
    function getActiveAuthByKey(string $authKey, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE authKey = :authKey 
                  AND validTill >= NOW()
                  AND enabled = 1",
            ['%cols%' => $cols],
            ['authKey' => $authKey]
        )->row();
    }

    protected function _insert(array $userId, array $data, int $sessionExpireMinutes): int
    {
        $this->beginTransaction();

        $this->_disable($userId);

        $lastId = $this->query(
            "INSERT INTO $this->_table 
            SET %set%, 
                validTill = ADDDATE(NOW(), INTERVAL $sessionExpireMinutes MINUTE),
                created = NOW()",
            ['%set%' => $this->filter($data)]
        )->exec()->lastId();

        $this->commit();

        return $lastId;
    }

    protected function _disable(array $userId)
    {
        $this->query(
            "UPDATE $this->_table 
            SET enabled = 0 
            WHERE %where% AND enabled = 1",
            ['%where%' => [$userId[0] => $userId[1]]]
        )->exec();
    }
}