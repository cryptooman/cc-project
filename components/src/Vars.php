<?php
/*
Variables extract and filter

Usage:
    Get from $_GET
        $_GET['name'] = 'George';
        $name = Vars::get('name');

    Get from $_GET and filter
        $_GET['name'] = 'george';
        $name = Vars::get('name', Vars::STR, [1, 30], function($v) { return ucfirst($v); });

    Get from $_POST
        Vars::post(...)

    Filter variable
        Vars::filter(...)

    Get from $_GET array (from input form) and filter
        $_GET['nums'] = [1, 5, 100];
        $nums = Vars::get('nums', Vars::ARR, [Vars::INT, [1, 100]], function($v) { return $v + 1; });

    <filter>
        INT|UINT|BIGINT|UBIGINT|FLOAT|UFLOAT    [0, 100]		    Range >= 0 && <= 100
                                                [0]		            Equal to range: 0, <default max value>
                                                []		            Equal to range: <default min value>, <default max value>

        STR 		[0, 100]                Length in bytes: >= 0 && <= 100 (min, max length optionally)

        MBSTR 		[0, 100]                Length in symbols: >= 0 && <= 100 (min, max length optionally)

        RAWSTR 		[0, 100]                Length in bytes: >= 0 && <= 100 (min, max length optionally)

        ENUM		['v1', 'v2']            Allowed values

        BOOL		[0, 1]                  Only 0 or 1 allowed (0, 1 optionally)

        REGX		['![a-z]{5}!i']		    Regular expression

        EMAIL 		[6, 50]                 Validate email (min, max length IN SYMBOLS optionally)

        HASH 		[32, 64]                Validate hexademical hash (min, max length optionally)

        BASE64 		[1, 4096]               Validate base64 string (min, max length optionally)

        BASE62 		[1, 4096]               Validate base62 string (min, max length optionally)

        IP		    --                      Validate ip

        URLID       [1, 255]                Validate url id (min, max length optionally)

        DATE 		['2000-01-01', '2038-01-01']                    Validate date in format "2000-01-01" (min, max date optionally)

        DATETIME 	['2000-01-01 00:00:01', '2038-01-01 23:59:59']  Validate datetime in format "2000-01-01 00:00:01" (min, max datetime optionally)

        ARR         Array of equal types

        ANY         Any type
*/
class Vars
{
	const INT		= "int";
	const UINT		= "uint";
	const BIGINT	= "bigInt";
	const UBIGINT	= "uBigInt";
	const FLOAT     = "float"; // NOTE: All float variables are of "double" gettype() in PHP
	const UFLOAT    = "uFloat";
	const STR		= "str";
	const MBSTR		= "mbStr";
	const RAWSTR	= "rawStr"; // No trim, no quote
	const ENUM		= "enum";
	const BOOL		= "bool";
	const REGX		= "regx";
	const EMAIL		= "email";
	const HASH		= "hash";
	const BASE64	= "base64";
	const BASE62	= "base62";
    const IP	    = "ip";
    const URLID	    = "urlId";
	const DATE		= "date";
	const DATETIME	= "dateTime";
	const ARR		= "array"; // Array of equal types
	const ANY	    = "anyType";

	const NO_DEFAULT_VALUE = "noDefaultValue929e9cf52aee08cd575d0fbfe0eae853";

	const INT_MIN_DEFAULT 		    = Cnst::INT32_MIN;
	const INT_MAX_DEFAULT 		    = Cnst::INT32_MAX;
	const UINT_MIN_DEFAULT 		    = Cnst::UINT32_MIN;
	const UINT_MAX_DEFAULT 		    = Cnst::UINT32_MAX;
	const BIGINT_MIN_DEFAULT 	    = Cnst::INT64_MIN;
	const BIGINT_MAX_DEFAULT 	    = Cnst::INT64_MAX;
	const UBIGINT_MIN_DEFAULT 	    = Cnst::UINT64_MIN;
	const UBIGINT_MAX_DEFAULT 	    = Cnst::INT64_MAX; // PHP int is always signed
    const FLOAT_MIN_DEFAULT         = Cnst::FLOAT_MIN;
    const FLOAT_MAX_DEFAULT 		= Cnst::FLOAT_MAX;
    const UFLOAT_MIN_DEFAULT        = Cnst::UFLOAT_MIN;
    const UFLOAT_MIN_NON_ZERO       = Cnst::UFLOAT_MIN_NON_ZERO;
    const UFLOAT_MAX_DEFAULT 		= Cnst::UFLOAT_MAX;
	const STR_MIN_LEN_DEFAULT 	    = 0;            // In bytes
	const STR_MAX_LEN_DEFAULT 	    = Cnst::INT32_MAX;
    const STR_APPLY_TRIM		    = true;         // Apply trim() to value
    const STR_APPLY_QUOTE		    = true;         // Apply Str::quote() to value (NOTE: To avoid XSS)
    const MBSTR_MIN_LEN_DEFAULT     = 0;            // In symbols
    const MBSTR_MAX_LEN_DEFAULT 	= 536870911;    // STR_MAX_LEN_DEFAULT / 4
    const MBSTR_APPLY_TRIM		    = true;
    const MBSTR_APPLY_QUOTE		    = true;
    const RAWSTR_MIN_LEN_DEFAULT    = 0;            // In bytes
    const RAWSTR_MAX_LEN_DEFAULT 	= Cnst::INT32_MAX;
    const ENUM_APPLY_QUOTE		    = true;
    const REGX_APPLY_QUOTE		    = true;
    const EMAIL_MIN_LEN_DEFAULT     = 6;            // In symbols. Min len 6 is equal to x@x.xx
    const EMAIL_MAX_LEN_DEFAULT     = 50;
    const HASH_MIN_LEN_DEFAULT      = 32;
    const HASH_MAX_LEN_DEFAULT      = 128;
    const BASE64_MIN_LEN_DEFAULT    = 1;
    const BASE64_MAX_LEN_DEFAULT    = 4096;
    const BASE62_MIN_LEN_DEFAULT    = 1;
    const BASE62_MAX_LEN_DEFAULT    = 4096;
    const URLID_MIN_LEN_DEFAULT     = 1;
    const URLID_MAX_LEN_DEFAULT     = 255;
    const DATE_MIN_DEFAULT          = '2000-01-01';
    const DATE_MAX_DEFAULT          = '2038-01-01';
    const DATETIME_MIN_DEFAULT      = '2000-01-01 00:00:00';
    const DATETIME_MAX_DEFAULT      = '2038-01-01 23:59:59';

    protected static $_varName;
    protected static $_value;
    protected static $_varType;
    protected static $_filter;
    protected static $_callback;
    protected static $_defaultValue;
    protected static $_error;
    protected static $_error_code = 0;

	// Get from $_GET and filter
	static function get($varName, $varType = self::ANY, array $filter = [], $callback = null, $defaultValue = self::NO_DEFAULT_VALUE)
	{
		$value = null;
		
		if(array_key_exists($varName, $_GET))
			$value = $_GET[$varName];
			
		return static::_process($varName, $value, $varType, $filter, $callback, $defaultValue);
	}

	// Get from $_POST and filter
	static function post($varName, $varType = self::ANY, array $filter = [], $callback = null, $defaultValue = self::NO_DEFAULT_VALUE)
	{
		$value = null;
		
		if(array_key_exists($varName, $_POST))
			$value = $_POST[$varName];
			
		return static::_process($varName, $value, $varType, $filter, $callback, $defaultValue);
	}

	// Filter variable
	static function filter($value, $varType = self::ANY, array $filter = [], $callback = null, $defaultValue = self::NO_DEFAULT_VALUE)
	{
		return static::_process($varName = '__', $value, $varType, $filter, $callback, $defaultValue);
	}

	// Get last filtering error
    static function getError()
    {
        $code = static::$_error_code;
        $msg = static::$_error;

        static::$_error_code = 0;
        static::$_error = '';

        return [$code, $msg];
    }

    protected static function _process($varName, $value, $varType, array $filter, $callback, $defaultValue)
	{
		if(!$varType)
			throw new VarsException("Bad [$varName]: Undefined type");

        if($varType === self::ANY)
            return $value;

        if($value === null)
        {
            if($defaultValue !== self::NO_DEFAULT_VALUE)
            {
                static::$_error_code = VarsException::E_EMPTY;
                return $defaultValue;
            }
            throw new VarsException("Bad [$varType:$varName]: Not defined or is null", VarsException::E_EMPTY);
        }

        static::$_varName = $varName;
        static::$_value = $value;
        static::$_varType = $varType;
        static::$_filter = $filter;
        static::$_callback = $callback;
        static::$_defaultValue = $defaultValue;

		if($varType === self::ARR)
		{
            $filter[0] = isset($filter[0]) ? $filter[0] : Vars::ANY;
            $filter[1] = isset($filter[1]) ? $filter[1] : [];

			if(!$value || !is_array($value))
			{
                if($defaultValue !== self::NO_DEFAULT_VALUE)
                {
                    static::$_error_code = VarsException::E_EMPTY;
                    return $defaultValue;
                }

                if($filter[0] === self::ANY)
                {
                    static::$_error_code = VarsException::E_EMPTY;
                    return array();
                }

                throw new VarsException("Bad [$varType:$varName]: Empty or not an array", VarsException::E_EMPTY);
			}
			foreach($value as &$val)
			{
			    if(!is_scalar($val))
                {
                    if($defaultValue !== self::NO_DEFAULT_VALUE)
                    {
                        static::$_error_code = VarsException::E_BAD_TYPE;
                        return $defaultValue;
                    }
                    throw new VarsException("Bad [$varType:$varName]: Elements must be scalars: ".print_r($value, 1), VarsException::E_BAD_TYPE);
                }

                static::$_value     = $val;
                static::$_varType   = $filter[0];
                static::$_filter    = $filter[1];

				$val = static::_filter_var();
			}
			unset($val);
			
			return $value;
		}
		else
        {
            return static::_filter_var();
        }
	}

    protected static function _filter_var()
	{
		$validate = array(
			self::INT 		=> array('_check_int'),
			self::UINT 		=> array('_check_uint'),
			self::BIGINT 	=> array('_check_big_int'),
			self::UBIGINT 	=> array('_check_ubig_int'),
			self::FLOAT 	=> array('_check_float'),
			self::UFLOAT 	=> array('_check_ufloat'),
			self::STR 		=> array('_check_str'),
			self::MBSTR 	=> array('_check_mbstr'),
			self::RAWSTR 	=> array('_check_rawstr'),
			self::ENUM 		=> array('_check_enum'),
			self::BOOL 		=> array('_check_bool'),
			self::REGX 		=> array('_check_regx'),
			self::EMAIL 	=> array('_check_email'),
			self::HASH 	    => array('_check_hash'),
			self::BASE64 	=> array('_check_base64'),
			self::BASE62 	=> array('_check_base62'),
            self::IP 	    => array('_check_ip'),
            self::URLID 	=> array('_check_url_id'),
			self::DATE 	    => array('_check_date'),
			self::DATETIME 	=> array('_check_datetime'),
			self::ANY 	    => array(),
		);

		if(!isset($validate[static::$_varType]))
			throw new VarsException("Bad [".static::$_varType.':'.static::$_varName ."] type: Unknown type");

        if(empty($validate[static::$_varType]) && static::$_varType !== self::ANY)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName ."] check method: Empty method");

		foreach($validate[static::$_varType] as $checkMethod)
		{
		    if(static::$_varType === self::ANY)
		        continue;

			if(!call_user_func(array(__CLASS__, $checkMethod)))
			{
				if(static::$_defaultValue === self::NO_DEFAULT_VALUE)
					throw new VarsException(static::$_error, static::$_error_code);
					
				return static::$_defaultValue;
			}
		}

        if(static::$_callback)
        {
            $callback = static::$_callback;
            $value_orig = static::$_value;

            static::$_value = $callback(static::$_value);
            if(static::$_value === null && static::$_varType !== self::ANY)
            {
                if(static::$_defaultValue === self::NO_DEFAULT_VALUE)
                    throw new VarsException(
                        "Bad [".static::$_varType.':'.static::$_varName."] value [".$value_orig."]: Failed at callback", VarsException::E_CALLBACK_FAILED
                    );

                return static::$_defaultValue;
            }
        }

		return static::$_value;
	}

    protected static function _check_int()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = isset($f[0]) && $f[0] !== null ? $f[0] : self::INT_MIN_DEFAULT;

        $max = isset($f[1]) && $f[1] !== null ? $f[1] : self::INT_MAX_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_numeric_type_int())
            return false;

        static::$_value = (int) static::$_value;

        if(!static::_check_numeric_range_int($min, $max))
            return false;

        return true;
    }

    protected static function _check_uint()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = isset($f[0]) && $f[0] !== null ? $f[0] : self::UINT_MIN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = isset($f[1]) && $f[1] !== null ? $f[1] : self::UINT_MAX_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_numeric_type_int())
            return false;

        static::$_value = (int) static::$_value;

        if(!static::_check_numeric_range_int($min, $max))
            return false;

        return true;
    }

    protected static function _check_big_int()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = isset($f[0]) && $f[0] !== null ? $f[0] : self::BIGINT_MIN_DEFAULT;

        $max = isset($f[1]) && $f[1] !== null ? $f[1] : self::BIGINT_MAX_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_numeric_type_int())
            return false;

        static::$_value = (int) static::$_value;

        if(!static::_check_numeric_range_int($min, $max))
            return false;

        return true;
    }

    protected static function _check_ubig_int()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = isset($f[0]) && $f[0] !== null ? $f[0] : self::UBIGINT_MIN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = isset($f[1]) && $f[1] !== null ? $f[1] : self::UBIGINT_MAX_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_numeric_type_int())
            return false;

        static::$_value = (int) static::$_value;

        if(!static::_check_numeric_range_int($min, $max))
            return false;

        return true;
    }

    protected static function _check_float()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = isset($f[0]) && $f[0] !== null ? $f[0] : self::FLOAT_MIN_DEFAULT;

        $max = isset($f[1]) && $f[1] !== null ? $f[1] : self::FLOAT_MAX_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_numeric_type_float())
            return false;

        static::$_value = (double) static::$_value;

        if(!static::_check_numeric_range_float($min, $max))
            return false;

        return true;
    }

    protected static function _check_ufloat()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = isset($f[0]) && $f[0] !== null ? $f[0] : self::UFLOAT_MIN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = isset($f[1]) && $f[1] !== null ? $f[1] : self::UFLOAT_MAX_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_numeric_type_float())
            return false;

        static::$_value = (double) static::$_value;

        if(!static::_check_numeric_range_float($min, $max))
            return false;

        return true;
    }

    protected static function _check_str()
	{
		$f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

		$min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::STR_MIN_LEN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

		$max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::STR_MAX_LEN_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_value_type())
            return false;

		if(self::STR_APPLY_TRIM)
            static::$_value = trim(static::$_value);

        if(!static::_check_str_length($min, $max))
            return false;

		if(self::STR_APPLY_QUOTE)
            static::$_value = Str::quote(static::$_value);

        return true;
	}

    protected static function _check_mbstr()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::MBSTR_MIN_LEN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::MBSTR_MAX_LEN_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_value_type())
            return false;

        if(self::MBSTR_APPLY_TRIM)
            static::$_value = trim(static::$_value);

        if(!static::_check_mbstr_length($min, $max))
            return false;

        if(self::MBSTR_APPLY_QUOTE)
            static::$_value = Str::quote(static::$_value);

        return true;
    }

    protected static function _check_rawstr()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::RAWSTR_MIN_LEN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::RAWSTR_MAX_LEN_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_value_type())
            return false;

        if(!static::_check_str_length($min, $max))
            return false;

        return true;
    }

    protected static function _check_enum()
	{
	    $f = static::$_filter;
	    if(!$f)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter: Empty filter");

        if(!static::_check_value_type())
            return false;

		if(!in_array(static::$_value, $f))
		{
            static::$_error_code = VarsException::E_MISMATCH_PATTERN;
			return static::_error(
			    "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Does not match enum [".join(', ', $f)."]"
            );
		}

        if(self::ENUM_APPLY_QUOTE)
            static::$_value = Str::quote(static::$_value);

		return true;	
	}

    protected static function _check_bool()
    {
        if(static::$_filter)
        {
            $f = static::$_filter;
            if(count($f) > 2)
                throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', static::$_filter)."]");

            $f = array_map(function(&$v) {
                $v = trim($v);
                if(!in_array($v, ['0', '1']))
                    throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter: Only 0 or 1 allowed");
                return $v;
            }, $f);
        }
        else
            $f = [0, 1];

        if(!static::_check_value_type())
            return false;

        if(!in_array(static::$_value, $f))
        {
            static::$_error_code = VarsException::E_MISMATCH_PATTERN;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Does not match bool [".join(",", $f)."]"
            );
        }

        return true;
    }

    protected static function _check_regx()
	{
		$f = static::$_filter;
		if(empty($f[0]))
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter: Empty filter");

        if(count($f) != 1)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        if(!static::_check_value_type())
            return false;

        if(!static::_check_regx_match($f[0]))
            return false;

        if(self::REGX_APPLY_QUOTE)
            static::$_value = Str::quote(static::$_value);

		return true;
	}

    protected static function _check_email()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::EMAIL_MIN_LEN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::EMAIL_MAX_LEN_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_value_type())
            return false;

        // NOTE: Used

        if(!static::_check_regx_match('!^[\p{L}\d_\.-]+?@[\p{L}\d_\.-]+?\.[\p{L}\d\.]{2,}$!u'))
            return false;

        if(!static::_check_mbstr_length($min, $max))
            return false;

        return true;
    }

    protected static function _check_hash()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::HASH_MIN_LEN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::HASH_MAX_LEN_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_value_type())
            return false;

        if(!static::_check_regx_match('!^[a-f\d]+$!'))
            return false;

        if(!static::_check_str_length($min, $max))
            return false;

        return true;
    }

    protected static function _check_base64()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::BASE64_MIN_LEN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::BASE64_MAX_LEN_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_value_type())
            return false;

        if(!static::_check_regx_match('!^[a-zA-Z\d\+/]*=*$!'))
            return false;

        if(!static::_check_str_length($min, $max))
            return false;

        return true;
    }

    protected static function _check_base62()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::BASE62_MIN_LEN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::BASE62_MAX_LEN_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_value_type())
            return false;

        if(!static::_check_regx_match('!^[a-zA-Z\d]*$!'))
            return false;

        if(!static::_check_str_length($min, $max))
            return false;

        return true;
    }

    protected static function _check_ip()
    {
        if(!static::_check_value_type())
            return false;

        if(!static::_check_regx_match('!^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$!'))
            return false;

        $numVal = (int) str_replace('.', '', static::$_value);
        if($numVal <= 0)
        {
            static::$_error_code = VarsException::E_BAD_VALUE;
            return static::_error("Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]");
        }
        elseif($numVal > 255255255255)
        {
            static::$_error_code = VarsException::E_BAD_VALUE;
            return static::_error("Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]");
        }

        return true;
    }

    protected static function _check_url_id()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::URLID_MIN_LEN_DEFAULT;
        if($min < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$min]");

        $max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::URLID_MAX_LEN_DEFAULT;
        if($max < $min)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$max]");

        if(!static::_check_value_type())
            return false;

        if(!static::_check_regx_match('!^[a-zA-Z\d\-]*$!'))
            return false;

        if(!static::_check_str_length($min, $max))
            return false;

        return true;
    }

    protected static function _check_date()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::DATE_MIN_DEFAULT;
        $minUtime = strtotime($min);
        if($minUtime < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$minUtime]");

        $max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::DATE_MAX_DEFAULT;
        $maxUtime = strtotime($max);
        if($maxUtime < $minUtime)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$maxUtime]");

        if(!static::_check_value_type())
            return false;

        if(!static::_check_regx_match('!^\d{4}-\d{2}-\d{2}$!'))
            return false;

        $utime = strtotime(static::$_value);
        if($utime < $minUtime)
        {
            static::$_error_code = VarsException::E_RANGE_LESS;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Less allowed min [$min]"
            );
        }
        elseif($utime > $maxUtime)
        {
            static::$_error_code = VarsException::E_RANGE_MORE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: More allowed max [$max]"
            );
        }

        return true;
    }

    protected static function _check_datetime()
    {
        $f = static::$_filter;
        if(count($f) > 2)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter [".join(', ', $f)."]");

        $min = (isset($f[0]) && $f[0] !== null) ? (int) $f[0] : self::DATETIME_MIN_DEFAULT;
        $minUtime = strtotime($min);
        if($minUtime < 0)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter min [$minUtime]");

        $max = (isset($f[1]) && $f[1] !== null) ? (int) $f[1] : self::DATETIME_MAX_DEFAULT;
        $maxUtime = strtotime($max);
        if($maxUtime < $minUtime)
            throw new VarsException("Bad [".static::$_varType.':'.static::$_varName."] filter max [$maxUtime]");

        if(!static::_check_value_type())
            return false;

        if(!static::_check_regx_match('!^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$!'))
            return false;

        $utime = strtotime(static::$_value);
        if($utime < $minUtime)
        {
            static::$_error_code = VarsException::E_RANGE_LESS;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Less allowed min [$min]"
            );
        }
        elseif($utime > $maxUtime)
        {
            static::$_error_code = VarsException::E_RANGE_MORE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: More allowed max [$max]"
            );
        }

        return true;
    }

    // Sub-checks

    protected static function _check_value_type(array $allowed_types = ['integer', 'double', 'string', 'boolean'])
    {
        $vtype = gettype(static::$_value);
        if(!in_array($vtype, $allowed_types))
            return static::_error("Bad [".static::$_varType.':'.static::$_varName."] value type [$vtype]");
        return true;
    }

    protected static function _check_numeric_type_int()
    {
        if(!static::_check_value_type())
            return false;

        $vtype = gettype(static::$_value);

        if(!is_numeric(static::$_value))
        {
            static::$_error_code = VarsException::E_BAD_TYPE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Invalid detected type [$vtype]"
            );
        }

        if(preg_match('/^-?0x[0-9a-f]+$/', static::$_value) || preg_match('/^-?0[0-7]+$/', static::$_value))
        {
            static::$_error_code = VarsException::E_BAD_TYPE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Must be in decimal form"
            );
        }

        // Cast notes:
        //      Good numeric string == (int) string (bad, like "12qwe" will be casted to "12")
        //      PHP_INT_MAX + 1 != (int) PHP_INT_MAX + 1 (will be 9223372036854775808 (or 9.2233720368548E+18) which is of "double" type)
        if(static::$_value != (int) static::$_value)
        {
            static::$_error_code = VarsException::E_BAD_TYPE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]"
            );
        }

        return true;
    }

    protected static function _check_numeric_type_float()
    {
        if(!static::_check_value_type())
            return false;

        $vtype = gettype(static::$_value);

        if(!is_numeric(static::$_value))
        {
            static::$_error_code = VarsException::E_BAD_TYPE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Invalid detected type [$vtype]"
            );
        }

        // Cast notes:
        //      Good double string == (double) string (bad, like "12.1qwe" will be casted to "12.1")
        if(static::$_value != (double) static::$_value)
        {
            static::$_error_code = VarsException::E_BAD_TYPE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]"
            );
        }

        return true;
    }

    protected static function _check_numeric_range_int(int $min, int $max)
    {
        if(static::$_value < $min)
        {
            static::$_error_code = VarsException::E_RANGE_LESS;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Less allowed min [$min]"
            );
        }
        elseif(static::$_value > $max)
        {
            static::$_error_code = VarsException::E_RANGE_MORE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: More allowed max [$max]"
            );
        }

        return true;
    }

    protected static function _check_numeric_range_float(float $min, float $max)
    {
        if(static::$_value < $min)
        {
            static::$_error_code = VarsException::E_RANGE_LESS;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Less allowed min [$min]"
            );
        }
        elseif(static::$_value > $max)
        {
            static::$_error_code = VarsException::E_RANGE_MORE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: More allowed max [$max]"
            );
        }

        return true;
    }

    protected static function _check_str_length(int $min, int $max)
    {
        if(strlen(static::$_value) < $min)
        {
            static::$_error_code = VarsException::E_RANGE_LESS;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Less allowed length min [$min]"
            );
        }
        elseif(strlen(static::$_value) > $max)
        {
            static::$_error_code = VarsException::E_RANGE_MORE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: More allowed length max [$max]"
            );
        }
        return true;
    }

    protected static function _check_mbstr_length(int $min, int $max)
    {
        if(mb_strlen(static::$_value) < $min)
        {
            static::$_error_code = VarsException::E_RANGE_LESS;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Less allowed length min [$min]"
            );
        }
        elseif(mb_strlen(static::$_value) > $max)
        {
            static::$_error_code = VarsException::E_RANGE_MORE;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: More allowed length max [$max]"
            );
        }
        return true;
    }

    protected static function _check_regx_match($regx)
    {
        if(!preg_match($regx, static::$_value))
        {
            static::$_error_code = VarsException::E_MISMATCH_PATTERN;
            return static::_error(
                "Bad [".static::$_varType.':'.static::$_varName."] value [".static::$_value."]: Does not match regx [$regx]"
            );
        }
        return true;
    }

    protected static function _error($err_str)
	{
        static::$_error = $err_str;
		return false;
	}
}