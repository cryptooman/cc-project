<?php
/**
 *
 */
abstract class ModelAbstractUsers extends Model
{
    function isEmailExists(string $email): bool
    {
        return (bool) $this->query(
            "SELECT id 
            FROM $this->_table 
            WHERE email = :email",
            ['email' => $email]
        )->value();
    }

    function approve(int $id)
    {
        $this->query(
            "UPDATE $this->_table 
            SET approved = 1
            WHERE id = :id AND approved = 0",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    function disapprove(int $id)
    {
        $this->query(
            "UPDATE $this->_table 
            SET approved = 0
            WHERE id = :id AND approved = 1",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    // NOTE: Only for manual confirmation via admin section (or for tests)
    function confirm(int $id)
    {
        $this->query(
            "UPDATE $this->_table 
            SET confirmed = 1
            WHERE id = :id AND confirmed = 0",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    function enable(int $id)
    {
        $this->_enableRowById($id);
    }

    function disable(int $id)
    {
        $this->_disableRowById($id);
    }

    protected function _getUsers(int $limit, array $cols = ['*']): array
    {
        return $this->_getRows($limit, $cols);
    }

    protected function _getUserById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE id = :id",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    protected function _getActiveUserById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            WHERE id = :id
                  AND approved = 1
                  AND confirmed = 1
                  AND enabled = 1",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    // NOTE: For authentication action
    protected function _getActiveUserByCredentials(string $email, string $passHash): array
    {
        return $this->query(
            "SELECT id, role
            FROM $this->_table
            WHERE email = :email
                  AND passHash = :passHash
                  AND approved = 1
                  AND confirmed = 1
                  AND enabled = 1",
            ['email' => $email, 'passHash' => $passHash]
        )->row();
    }

    protected function _insert(array $data, string $confirmKey, ModelAbstractRegisterConfirms $mRegConfirms): int
    {
        $this->beginTransaction();

        $userId = $this->query(
            "INSERT INTO $this->_table 
            SET %set%, created = NOW()",
            ['%set%' => $this->filter($data)]
        )->exec()->lastId();

        $mRegConfirms->insert($userId, $confirmKey);

        $this->commit();

        return $userId;
    }

    protected function _confirmRegistration(int $id, int $regConfirmId, ModelAbstractRegisterConfirms $mRegConfirms)
    {
        $this->beginTransaction();

        $this->query(
            "UPDATE $this->_table 
            SET confirmed = 1
            WHERE id = :id
                  AND confirmed = 0 
                  AND enabled = 1",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);

        $mRegConfirms->confirm($regConfirmId);

        $this->commit();
    }
}