<?php
/**
 *
 */
abstract class ModelAbstractRegisterConfirms extends Model
{
    function getActiveConfirmByKey(string $confirmKey, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE confirmKey = :confirmKey 
                  AND validTill >= NOW()
                  AND confirmed = 0
                  AND enabled = 1",
            ['%cols%' => $cols],
            ['confirmKey' => $confirmKey]
        )->row();
    }

    function confirm(int $id)
    {
        $this->query(
            "UPDATE $this->_table 
            SET confirmed = 1
            WHERE id = :id
                  AND validTill >= NOW()
                  AND confirmed = 0
                  AND enabled = 1",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _countConfirmsByUserId(array $userId): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) 
            FROM $this->_table 
            WHERE %where%",
            ['%where%'=> [$userId[0] => $userId[1]]]
        )->value();
    }

    protected function _insert(array $userId, string $confirmKey, int $confirmRowsPerAdminMax, int $confirmRowExpireMinutes): int
    {
        $rows = $this->_countConfirmsByUserId($userId);
        if ($rows >= $confirmRowsPerAdminMax) {
            throw new Err("Max allowed confirm rows [$rows] reached for [%s]", $userId);
        }

        $this->beginTransaction();

        $this->_disable($userId);

        $lastId = $this->query(
            "INSERT INTO $this->_table 
            SET %set%, 
                validTill = ADDDATE(NOW(), INTERVAL $confirmRowExpireMinutes MINUTE),
                created = NOW()",
            ['%set%' => $this->filter([
                'confirmKey' => $confirmKey,
                $userId[0] => $userId[1],
            ])]
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