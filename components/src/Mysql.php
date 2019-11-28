<?php
/*
Usage:
    $m = new Mysql( ... );

    $res = $m->query("SELECT * FROM users")->rows();

    $res = $m->query("SELECT * FROM users WHERE id = ?", $userId)->row();

    $res = $m->query("SELECT name FROM users WHERE id = ?", $userId)->value();

    $res = $m->query("SELECT * FROM users WHERE id = :id", ['id' => $userId])->row();

    $res = $m->query("SELECT %cols% FROM users WHERE id = :id", ['%cols%' => ['*']], ['id' => $userId])->row();

    $m->query(
        "INSERT INTO users SET %set%, created = NOW() WHERE %where%",
        ['%set%' => ['name' => $userName]],
        ['%where%' => ['id' => $userId]],
    )->exec();

    Transaction
        $m->beginTransaction();
        $m->query(" ... ")->exec();
        $m->commit();

    Bulk insert ( INSERT INTO <table> VALUES (...), (...), ... )
        $m->insertBulk(
            "INSERT INTO users VALUES %values%",        - Query
            [':id', ':name'],                           - Query values macros
            [                                           - Data
                ['id' => '1', 'name' => 'Name 1'],
                ['id' => '2', 'name' => 'Name 2']
            ]
        );

    Procedures
        $res = $m->queryCall($procedure)->rows();

    NOTE: For queries with "%" use further syntax
        $m->query(" ... WHERE date LIKE('%%-00%%')")
*/
class Mysql
{
    const CONNECTION_ATTEMPTS       = 3;
    const ENCODING                  = 'UTF8';
    const ENCODING_ICONV_IN         = 'UTF-8';
    const ENCODING_ICONV_OUT        = 'UTF-8//IGNORE';
    const MACRO_SERIAL              = '?';
    const MACRO_ASSOC_PREFIX        = ':';
    const MACRO_VALUE_QUOTE         = "'"; // NOTE: Single quotes are applied internally by PDO::quote() at _quoteValue()
    const MACRO_VALUE_NULL          = 'NULL';
    const MACRO_EXPAND_COLS         = '%cols%';
    const MACRO_EXPAND_SET          = '%set%';
    const MACRO_EXPAND_WHERE        = '%where%';
    const COLUMN_NAME_SYMBOLS       = 'a-zA-Z0-9_\.';
    const MACRO_NAME_SYMBOLS        = '[_a-zA-Z][a-zA-Z0-9_\.]*';

    protected $_host;
    protected $_port;
    protected $_user;
    protected $_pass;
    protected $_database;
    protected $_conn;
    protected $_query;
    protected $_query_tpl;
    protected $_query_last;
    protected $_affected_rows;

    function __construct($host, $user, $pass, $database, $port = 3306)
    {
        // NOTE: Password can be empty (at local env)
        if (!$host || !$user || !$database || !$port) {
            throw new MysqlException("Bad connection params (host [$host] port [$port] user [$user] database [$database])");
        }
		$this->_host = $host;
		$this->_port = $port;
        $this->_user = $user;
        $this->_pass = $pass;
        $this->_database = $database;

        $this->_connect();
    }

    // Args: <sql-query>, [substitute-1], [substitute-2], ...
    function query(...$args)
    {
        return $this->_queryBuild(...$args);
    }

	function queryCall(...$args)
    {
        $procedure_name = array_shift($args);
        
        if(isset($args[0]) && is_array($args[0]))
			$args = $args[0];
        
		$param = "";
        
        if(!empty($args))
        {
			$param = join(", ", array_fill(0, count($args), "?"));
        }
        
		$query = "CALL $procedure_name($param)";

        $this->_query = $query;
		$this->_query_tpl = $query;
		$this->_query_last = $query;

        $this->_setSerialMacros($args);
		
        return $this;
    }
    
	function rows()
    {
        $res = $this->_queryExec()->fetchAll(PDO::FETCH_ASSOC);

        $this->_free();
        
        if(!$res)
        	return array();
        	
        return $res;
    }
    
    function row()
    {
		$res = $this->_queryExec()->fetch(PDO::FETCH_ASSOC);

		$this->_free();
		
		if(!$res)
        	return array();
		
        return $res;
    }
    
    function value()
    {
        $res = $this->_queryExec()->fetchAll(PDO::FETCH_COLUMN);
        
        if(is_array($res))
        {
        	$res = isset($res[0]) ? $res[0] : null;
        }
        else 
        	$res = null;

        $this->_free();
        	
        return $res;
    }
    
    function exec()
    {
		$attempt = 10;
        $usleep = 100000;
		
       	while($attempt--) 
        {
        	$this->_checkConnect();

        	$err_code = null;
        	
        	if(($this->_affected_rows = $this->_conn->exec($this->_query)) === false)
			{
				$pdo_err = $this->_conn->errorInfo();

				$err_code = isset($pdo_err[1]) ? (int) $pdo_err[1] : null;

				if($err_code == MysqlException::E_DEAD_LOCK)
                {
                    usleep($usleep);
                    continue;
                }
                if($err_code == MysqlException::E_LOCK_WAIT_TIMEOUT)
                {
                    usleep($usleep);
                    continue;
                }
                if($err_code == MysqlException::E_SERVER_HAS_GONE)
                {
                    sleep(1);
                    continue;
                }
                if($err_code == MysqlException::E_LOST_CONNECTION)
                {
                    sleep(1);
                    continue;
                }
				
	        	throw new MysqlException("Impossible query: ".$this->_query, MysqlException::E_DEFAULT, $this->_conn);
			}
			
			break;
        }

        if(isset($err_code) && $err_code)
    		throw new MysqlException("Impossible query: ".$this->_query, MysqlException::E_DEFAULT, $this->_conn);
		
		$this->_free();
		
		return $this;
    }
    
    function beginTransaction()
    {
        if($this->inTransaction())
            throw new MysqlException("Already in transaction");

        try
        {
            $this->_conn->beginTransaction();
        }
        catch(Exception $e)
        {
            throw new MysqlException("Unable to start transaction: ".$e->getMessage(), MysqlException::E_DEFAULT, $this->_conn);
        }

        return $this;
    }
    
    function commit()
    {
        if(!$this->inTransaction())
            throw new MysqlException("No active transaction to commit");

        try
        {
            $this->_conn->commit();
        }
		catch(Exception $e)
        {
            throw new MysqlException("Unable to commit transaction: ".$e->getMessage(), MysqlException::E_DEFAULT, $this->_conn);
        }

        return $this;
    }

    function inTransaction()
    {
        return (bool) $this->_conn->inTransaction();
    }

    function rollback()
    {
        if($this->inTransaction() && $this->_conn->rollBack() === false)
            throw new MysqlException("Unable to rollback transaction", MysqlException::E_DEFAULT, $this->_conn);

        return $this;
    }

    // Returns original query string
    function str()
    {
        return $this->_query_tpl;
    }
    
    // Returns formatted query string
    function strf()
    {
        return $this->_query;
    }

    // Returns last formatted query string (if $this->_query was emptied by $this->_free())
    function strfLast()
    {
        return $this->_query_last;
    }

    // Returns value of last autoincrement field
    function lastId()
    {
        return (int) $this->_conn->lastInsertId();
    }
    
    // Return amount of affected rows during last execution
    function affectedRows()
    {
    	return (int) $this->_affected_rows;
    }
    
	function insertBulk($query_tpl, array $query_macros, array $data, $bulk_size = 100, $echoQueriesOnly = false)
	{
		if(!$data)
			throw new MysqlException("Empty data for bulk insert [$query_tpl]");
		
		$query = "";
		$query_val = array();
        $query_macros = join(', ', $query_macros);
		$data_len = count($data);
        $inserted_rows = 0;

		for($i = 1; $i <= $data_len; $i++)
		{
			$data_key = array();
			$data_value = array();

			foreach($data[$i-1] as $k_name => $v_data)
			{
				$data_key[] = self::MACRO_ASSOC_PREFIX.$k_name;
                $data_value[] = ($v_data !== null) ? $this->_quoteValue($v_data) : self::MACRO_VALUE_NULL;
			}
			unset($k_name, $v_data);

			$query_val[] = "(".str_replace($data_key, $data_value, $query_macros).")";
				
			if( !($i % (int) $bulk_size) || $i == $data_len)
			{
                $query = str_replace("%values%", join(', ', $query_val), $query_tpl);
				$query_val = array();

                $this->_query = $query;
                $this->_query_tpl = $query;
                $this->_query_last = $query;

				if($echoQueriesOnly)
                    echo $this->str()."\n";
				else
                    $inserted_rows += $this->exec()->affectedRows();
			}
		}

		return $inserted_rows;
	}

	function quoteValue($value)
	{
		return $this->_quoteValue($value);
	}

    function ping()
    {
        $this->_checkConnect();

        if(!$this->_conn->query('SELECT 1')->fetchColumn())
            throw new MysqlException("Mysql ping failed", MysqlException::E_DEFAULT, $this->_conn);

        return $this;
    }

    function reconnect()
    {
        $this->_connect();
        return $this;
    }

    protected function _connect()
    {
        $this->_conn = null;

        $error = '';
        for($i = 0; $i < self::CONNECTION_ATTEMPTS; $i++)
        {
            try 
            {
            	$conn_str = ($this->_database)
                            ? "mysql:host=$this->_host;port=$this->_port;dbname=$this->_database"
                            : "mysql:host=$this->_host;port=$this->_port";
            	
                $this->_conn = new PDO($conn_str, $this->_user, $this->_pass);

                if(is_object($this->_conn))
                    break;
            }
            catch(Exception $e)
            {
                $error = $e->getMessage();
            }
            
            sleep(1);
        }
        
        if(!is_object($this->_conn))
            throw new MysqlException("Failed to connect to host [$this->_host] on a database [$this->_database] with error [$error]");

        // Set PDO attributes
        // http://php.net/manual/en/pdo.setattribute.php
        foreach([
            [PDO::ATTR_CASE,                PDO::CASE_NATURAL],
            [PDO::ATTR_ERRMODE,             PDO::ERRMODE_SILENT],
            [PDO::ATTR_ORACLE_NULLS,        PDO::NULL_NATURAL],
            [PDO::ATTR_STRINGIFY_FETCHES,   false], // NOTE: "decimal" type will be returned as "string"
            // PDO::ATTR_STATEMENT_CLASS
            // PDO::ATTR_TIMEOUT
            // PDO::ATTR_AUTOCOMMIT
            [PDO::ATTR_EMULATE_PREPARES,    false],
            // PDO::MYSQL_ATTR_USE_BUFFERED_QUERY
            // PDO::ATTR_DEFAULT_FETCH_MODE
        ] as $attr)
        {
            if($this->_conn->setAttribute($attr[0], $attr[1]) === false)
                throw new MysqlException("Failed to set PDO attribute [".$attr[0]."] with value [".$attr[1]."]", MysqlException::E_DEFAULT, $this->_conn);
        }

        if($this->_conn->exec("SET NAMES ".self::ENCODING) === false)
            throw new MysqlException("Failed to set encoding [".self::ENCODING."]", MysqlException::E_DEFAULT, $this->_conn);
    }

    protected function _checkConnect()
    {
        if(!is_object($this->_conn))
            $this->_connect();
    }

    protected function _queryBuild(...$args)
    {
        $query = array_shift($args);

        $query = trim($query);
        if (!$query) {
            throw new MysqlException("Query is empty");
        }

        $assocMacros = [];
        $serialMacros = [];
        $colNameCheckRegx = '!^[' . self::COLUMN_NAME_SYMBOLS . '\* ]+$!'; // SELECT *, c AS count FROM ...
        $macroNameCheckRegx = '!^' . self::MACRO_NAME_SYMBOLS . '$!';
        foreach ($args as $arg) {
            if (is_array($arg)) {
                if (isset($arg[self::MACRO_EXPAND_COLS])) {
                    foreach ($arg[self::MACRO_EXPAND_COLS] as $_ => $name) {
                        if (!preg_match($colNameCheckRegx, $name)) {
                            throw new MysqlException("Bad column name [$name]");
                        }
                    }
                    $query = str_replace(self::MACRO_EXPAND_COLS, join(', ', $arg[self::MACRO_EXPAND_COLS]), $query);
                }
                elseif (isset($arg[self::MACRO_EXPAND_SET])) {
                    $argsExpanded = [];
                    foreach ($arg[self::MACRO_EXPAND_SET] as $name => $val) {
                        if (!preg_match($macroNameCheckRegx, $name)) {
                            throw new MysqlException("Bad macro name [$name]");
                        }
                        if (isset($assocMacros[$name])) {
                            throw new MysqlException("Macro [$name] already seen");
                        }
                        $assocMacros[$name] = $val;
                        $argsExpanded[] = "$name = " . self::MACRO_ASSOC_PREFIX.$name;
                    }
                    $query = str_replace(self::MACRO_EXPAND_SET, join(', ', $argsExpanded), $query);
                }
                elseif (isset($arg[self::MACRO_EXPAND_WHERE])) {
                    $argsExpanded = [];
                    foreach ($arg[self::MACRO_EXPAND_WHERE] as $name => $val) {
                        if (!preg_match($macroNameCheckRegx, $name)) {
                            throw new MysqlException("Bad macro name [$name]");
                        }
                        if (isset($assocMacros[$name])) {
                            throw new MysqlException("Macro [$name] already seen");
                        }
                        $assocMacros[$name] = $val;
                        $argsExpanded[] = "$name = " . self::MACRO_ASSOC_PREFIX.$name;
                    }
                    $query = str_replace(self::MACRO_EXPAND_WHERE, join(' AND ', $argsExpanded), $query);
                }
                else {
                    foreach ($arg as $name => $val) {
                        if (!preg_match($macroNameCheckRegx, $name)) {
                            throw new MysqlException("Bad macro name [$name]");
                        }
                        if (isset($assocMacros[$name])) {
                            throw new MysqlException("Macro [$name] already seen");
                        }
                        $assocMacros[$name] = $val;
                    }
                }
            }
            else {
                $serialMacros[] = $arg;
            }
        }

        $this->_query = $query;
        $this->_query_tpl = $query;
        $this->_query_last = $query;

        if ($assocMacros) {
            $this->_setAssocMacros($assocMacros);
        }
        if($serialMacros) {
            $this->_setSerialMacros($serialMacros);
        }

        return $this;
    }

    protected function _queryExec()
    {
        $this->_checkConnect();
        
        if(($r_pdo = $this->_conn->query($this->_query)) === false)
            throw new MysqlException("Impossible query: $this->_query", MysqlException::E_DEFAULT, $this->_conn);

        return $r_pdo;
    }

    protected function _setSerialMacros(array $values)
    {
		foreach ($values as &$v) {
            $v = ($v !== null) ? $this->_quoteValue($v) : self::MACRO_VALUE_NULL;
        }
		unset($v);

        $query = str_replace(self::MACRO_SERIAL, '%s', $this->_query);

		if (!($query = vsprintf($query, $values))) {
            throw new MysqlException("Query serial macro error: $this->_query", MysqlException::E_DEFAULT, $this->_conn);
        }

        $this->_query = $query;
        $this->_query_last = $query;
    }

    protected function _setAssocMacros(array $values)
    {
        // NOTE: Used query chunks to avoid unexpected replacements in already replaced macro

        // Add space: colName=:value -> colName= :value
        $query = preg_replace(
            '!([^ ])(' . self::MACRO_ASSOC_PREFIX . self::MACRO_NAME_SYMBOLS . ')!', '$1 $2', $this->_query
        );
        if (!$query) {
            throw new MysqlException("Query is empty");
        }
        $query = explode(' ', $query);

        foreach ($values as $mKey => $mVal) {
            $mKey = self::MACRO_ASSOC_PREFIX . $mKey;
            $replaced = false;
            foreach ($query as &$chunk) {
                if (empty($chunk[0]) || $chunk[0] != self::MACRO_ASSOC_PREFIX) {
                    continue;
                }
                $chunk = preg_replace_callback('!' . preg_quote($mKey) . '\b!', function() use($mVal) {
                    return ($mVal !== null) ? $this->_quoteValue($mVal) : self::MACRO_VALUE_NULL;
                }, $chunk, -1, $replacesCount);
                if (!$chunk) {
                    throw new MysqlException("Empty query chunk macro [$mKey]");
                }
                if ($replacesCount) {
                    $replaced = true;
                }
            }
            unset($chunk);

            if (!$replaced) {
                throw new MysqlException("No assoc macro [$mKey] in query: " . $this->_query);
            }
        }

        $query = join(' ', $query);

        $this->_query = $query;
        $this->_query_last = $query;
    }

    /*
    Tests (NOTE: For valid results must be tested through _setAssocMacros() and _setSerialMacros())
        $badValues = [
            //null, -> Must be an exception

            "'",
            "''",
            '\'',
            "\'",
            '\\\'',
            "\x27",

            '"',
            '""',
            '\"',
            "\"",
            "\\\"",
            "\x22",

            '\\',
            '\\\\',
            "\0",
            "\x00",
            "\n",
            "\x1a",

            '123 OR 1=1',
            '123; DROP ...',
            "'; DROP ..."
            "\'; DROP ..."
            "\x27; DROP ..."
            "\x00; DROP ..."
            "' OR '1'='1",
            '" OR ""="',
        ];
    */
    protected function _quoteValue($value)
    {
        $vtype = gettype($value);
        if (!in_array($vtype, array('integer', 'double', 'string'))) {        
            throw new MysqlException("Bad type [$vtype] of value [" . ErrHandler::cutLen($value) . "]: Expected numeric or string");
        }

        if (!mb_check_encoding($value)) {
            // Added "@" for ignoring notice "PHP Notice:  iconv(): Detected an illegal character in input string"
            $value = @iconv(self::ENCODING_ICONV_IN, self::ENCODING_ICONV_OUT, $value);
            if($value === false)
                throw new MysqlException("Failed to fix encoding for value [" . ErrHandler::cutLen($value) . "]");
        }
        
        // Single quotes are applied internally
        // Using quotes for all data types, also for numeric (PDO::PARAM_STR)
        $value = $this->_conn->quote($value, PDO::PARAM_STR);

        // Check value is properly quoted
        $len = strlen($value);
        if (
            $value[0] !== self::MACRO_VALUE_QUOTE
            || $value[$len - 1] !== self::MACRO_VALUE_QUOTE
            || ($value[$len - 2] === '\\' && $value[$len - 3] !== '\\') // No unescaped tail \

        ) {
            throw new MysqlException("Bad quoted value [" . ErrHandler::cutLen($value) . "]");
        }
        
        return $value;
    }

    protected function _free()
    {
    	$this->_query = null;
    	$this->_query_tpl = null;
    }
}