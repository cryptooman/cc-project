<?php
/**
 * Usage:
 *      class ModelUsers extends Model
 *      {
 *          protected $_table = 'users';
 *      }
 *      Model::init( ... );
 *      $rows = ModelUsers::inst()->getUsers();
 *
 *      Connection to another database in one project:
 *          class ClassModelDatabase2 extends Model
 *          {
 *              // NOTE: All static attributes of parent class must be re-initialized;
 *              protected static $_host;
 *              protected static $_port;
 *              protected static $_user;
 *              protected static $_pass;
 *              protected static $_database;
 *              protected static $_mysqlInstance;
 *              protected static $_inited = false;
 *          }
 *          class ModelUsers2 extends ClassModelDatabase2
 *          {
 *              protected $_table = 'users';
 *          }
 *          ClassModelDatabase2::init( ... );
 *          $rows = ModelUsers2::inst()->getUsers();
 */
class Model
{
    const DATETIME_EMPTY = '0000-00-00 00:00:00';

    const LIMIT_1 = 1;
    const LIMIT_10 = 10;
    const LIMIT_100 = 100;
    const LIMIT_MAX = PHP_INT_MAX;

    const AFFECTED_ANY = -1;
    const AFFECTED_ONE = 1;
    const AFFECTED_ONE_OR_MORE = -2;

    const LAST_ID_EXPECT = true;
    const LAST_ID_ANY = false;

    protected static $_host;
    protected static $_port;
    protected static $_user;
    protected static $_pass;
    protected static $_database;
    protected static $_mysqlInstance;
    protected static $_transactionOwnerKey;
    protected static $_inited;

    protected $_mysql;
    protected $_table;
    protected $_fields = [];

    static function init(string $host, string $user, string $pass, string $database, int $port = 3306)
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        // NOTE: Password can be empty (at local env)
        if (!$host || !$user || !$database || !$port) {
            throw new Err("Bad connection params: host [$host] port [$port] user [$user] database [$database]");
        }

        static::$_host = $host;
        static::$_port = $port;
        static::$_user = $user;
        static::$_pass = $pass;
        static::$_database = $database;
    }

    // NOTE: Each call returns child class new instance (mysql connection remains the same)
    static function inst(): Model
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return new static();
    }

    protected function __construct()
    {
        if (get_called_class() != __CLASS__) {
            if (!$this->_table) {
                throw new Err("Empty table name");
            }
            if (!$this->_fields) {
                throw new Err("Empty fields");
            }
        }
        if (!static::$_mysqlInstance) {
            static::$_mysqlInstance = new Mysql(
                static::$_host, static::$_user, static::$_pass, static::$_database, static::$_port
            );
        }
        $this->_mysql = static::$_mysqlInstance;
    }

    function table(): string
    {
        return $this->_table;
    }

    function filter(array $data): array
    {
        if (!$data) {
            throw new Err("Data is empty");
        }
        foreach ($data as $name => &$value) {
            $value = $this->filterOne($name, $value);
        } unset($value);

        return $data;
    }

    function filterOne(string $name, $value, bool $exception = true)
    {
        if (!isset($this->_fields[$name])) {
            throw new Err("Unknown field [$name]");
        }
        $filter = $this->_fields[$name];

        $filterCount = count($filter);
        if ($filterCount < 0 || $filterCount > 4) {
            throw new Err("Bad filtering args for field [$name]");
        }

        try {
            $valueOrig = $value;
            if ($filterCount == 4) {
                $value = Vars::filter($value, $filter[0], $filter[1], $filter[2], $filter[3]);
            }
            elseif ($filterCount == 3) {
                $value = Vars::filter($value, $filter[0], $filter[1], $filter[2]);
            }
            elseif ($filterCount == 2) {
                $value = Vars::filter($value, $filter[0], $filter[1]);
            }
            elseif ($filterCount == 1) {
                $value = Vars::filter($value, $filter[0]);
            }
            elseif ($filterCount == 0) {
                $value = Vars::filter($value);
            }
        }
        catch (Exception $e) {
            if ($exception) {
                throw new Err("Bad field [$name] value [$valueOrig]: ", $e->getMessage());
            }
            return null;
        }

        return $value;
    }

    // Mysql methods

    function query(...$args): Model
    {
        $this->_mysql->query(...$args);
        return $this;
    }

    function queryCall(...$args): Model
    {
        $this->_mysql->queryCall(...$args);
        return $this;
    }

    function rows(): array
    {
        return $this->_mysql->rows();
    }

    function row(): array
    {
        return $this->_mysql->row();
    }

    function value()
    {
        return $this->_mysql->value();
    }

    function exec()
    {
        $this->_mysql->exec();
        return $this;
    }

    function beginTransaction(): Model
    {
        $table = ($this->_table) ? $this->_table : '%%' . __CLASS__ . '%%';
        if (!static::$_transactionOwnerKey) {
            static::$_transactionOwnerKey = $table;
        }
        if (static::$_transactionOwnerKey == $table) {
            $this->_mysql->beginTransaction();
        }
        return $this;
    }

    function commit(): Model
    {
        $table = ($this->_table) ? $this->_table : '%%' . __CLASS__ . '%%';
        if (!static::$_transactionOwnerKey) {
            throw new Err("Empty transaction owner key");
        }
        if (static::$_transactionOwnerKey == $table) {
            $this->_mysql->commit();
            static::$_transactionOwnerKey = null;
        }
        return $this;
    }

    function inTransaction(): bool
    {
        return $this->_mysql->inTransaction();
    }

    function rollback(): Model
    {
        $this->_mysql->rollback();
        return $this;
    }

    function str(): string
    {
        return (string) $this->_mysql->str();
    }

    function strf(): string
    {
        return (string) $this->_mysql->strf();
    }

    function strfLast(): string
    {
        return (string) $this->_mysql->strfLast();
    }

    function lastId(bool $expectInserted = self::LAST_ID_EXPECT): int
    {
        $lastId = $this->_mysql->lastId();
        if ($expectInserted && !$lastId) {
            throw new Err("Last id is empty" . PHP_EOL . "Query: " . $this->_mysql->strfLast());
        }
        return $lastId;
    }

    function affectedRows(int $expectAffected): int
    {
        $affected = $this->_mysql->affectedRows();
        if ($expectAffected == self::AFFECTED_ANY) {
            // Skip check
        }
        elseif ($expectAffected == self::AFFECTED_ONE_OR_MORE) {
            if (!$affected) {
                throw new Err(
                    "Bad affected rows: affected [$affected] < expectAffected [>=1]" . PHP_EOL . "Query: " . $this->_mysql->strfLast()
                );
            }
        }
        else {
            if ($affected !== $expectAffected) {
                throw new Err(
                    "Bad affected rows: affected [$affected] != expectAffected [$expectAffected]" . PHP_EOL . "Query: " . $this->_mysql->strfLast()
                );
            }
        }
        return $affected;
    }

    function insertBulk(string $query, array $queryMacros, array $data, int $bulkSize = 100, bool $echoQueriesOnly = false): int
    {
        return $this->_mysql->insertBulk($query, $queryMacros, $data, $bulkSize, $echoQueriesOnly);
    }

    function quoteValue($value): string
    {
        return $this->_mysql->quoteValue($value);
    }

    function ping(): Model
    {
        $this->_mysql->ping();
        return $this;
    }

    function reconnect(): Model
    {
        $this->_mysql->reconnect();
        return $this;
    }

    // END: Mysql methods

    function getWarnings(int $limit = PHP_INT_MAX): array
    {
        return $this->query("SHOW WARNINGS LIMIT $limit")->rows();
    }

    // Helpers

    protected function _getRows(int $limit, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table
            ORDER BY id ASC 
            LIMIT $limit",
            ['%cols%' => $cols]
        )->rows();
    }

    protected function _getRowById(int $id, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE id = :id",
            ['%cols%' => $cols],
            ['id' => $id]
        )->row();
    }

    protected function _enableRowById(int $id)
    {
        $this->query(
            "UPDATE $this->_table 
            SET enabled = 1
            WHERE id = :id AND enabled = 0",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _disableRowById(int $id)
    {
        $this->query(
            "UPDATE $this->_table 
            SET enabled = 0
            WHERE id = :id AND enabled = 1",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _deleteRowById(int $id)
    {
        $this->query(
            "DELETE FROM $this->_table 
            WHERE id = :id",
            ['id' => $id]
        )->exec()->affectedRows(self::AFFECTED_ONE);
    }

    protected function _quoteIn(array $values): string
    {
        $in = [];
        foreach ($values as $value) {
            $in[] = $this->quoteValue($value);
        }
        return join(', ', $in);
    }

}
