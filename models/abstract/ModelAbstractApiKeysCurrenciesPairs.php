<?php
/**
 *
 */
abstract class ModelAbstractApiKeysCurrenciesPairs extends Model
{
    function getActivePairsByApiKeyId(int $apiKeyId, int $limit, array $cols = ['*']): array
    {
        return $this->_getActivePairsByWhere(['apiKeyId' => $apiKeyId], $limit, $cols);
    }

    function getActivePairByApiKeyIdPairId(int $apiKeyId, int $currPairId, array $cols = ['*']): array
    {
        return $this->_getActivePairByWhere(
            ['apiKeyId' => $apiKeyId, 'currPairId' => $currPairId], $cols
        );
    }

    protected function _getActivePairsByWhere(array $where, int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where% AND enabled = 1
            ORDER BY id ASC
            LIMIT $limit",
            ['%cols%' => $cols],
            ['%where%' => $where]
        )->rows();
    }

    protected function _getActivePairByWhere(array $where, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE %where% AND enabled = 1",
            ['%cols%' => $cols],
            ['%where%' => $where]
        )->row();
    }

    protected function _insert(array $data): int
    {
        return $this->query(
            "INSERT INTO $this->_table
            SET %set%, created = NOW()",
            ['%set%' => $this->filter($data)]
        )->exec()->lastId();
    }
}