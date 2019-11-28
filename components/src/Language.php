<?php
/**
 * Usage:
 *      Create translate file "translates/ru"
 *          # App
 *          Good morning!
 *          Доброе утро!
 *
 *          Current date is %s time is %s
 *          Сегодняшняя дата %s время %s
 *
 *          # Errors
 *          Error occurred
 *          Произошла ошибка
 *
 *      Language::init( ... );
 *      Language::makeTranslateCache();
 *      $res = Language::translate('Good morning!');
 *      $res = Language::translateMacro('Current date is %s time is %s', date('Y-m-d'), date('H:i:s'));
 */
function _t($keyStr, ...$args)
{
    return Language::translateMacro($keyStr, ...$args);
}

class Language
{
    const EN = 'en';
    const RU = 'ru';
    const FR = 'fr';
    const IT = 'it';
    const DE = 'de';
    const ES = 'es';

    protected static $_langDefault;
    protected static $_sourceDir;
    protected static $_cacheDir;
    protected static $_inited;

    static function init(string $langDefault, string $sourceDir, string $cacheDir)
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        static::validateLang($langDefault);
        static::$_langDefault = $langDefault;

        if (!is_dir($sourceDir)) {
            throw new Err("Not a dir [$sourceDir]");
        }
        static::$_sourceDir = $sourceDir;

        if (!is_dir($cacheDir)) {
            throw new Err("Not a dir [$cacheDir]");
        }
        static::$_cacheDir = $cacheDir;
    }

    static function validateLang(string $lang)
    {
        // NOTE: Check of static::$_inited is not need here

        if (!in_array($lang, [
            self::EN, self::RU, self::FR, self::IT, self::DE, self::ES
        ])) {
            throw new Err("Unknown lang [$lang]");
        }
    }

    static function makeTranslateCache(string $lang = null, bool $reset = false)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!$lang) {
            $lang = static::$_langDefault;
        }
        else {
            static::validateLang($lang);
        }

        $sourceFile = static::$_sourceDir. '/' . $lang;
        $cacheFile = static::$_cacheDir. '/' . $lang;

        if((!is_file($cacheFile) && is_file($sourceFile)) || $reset) {
            $content = file_get_contents($sourceFile);

            // Multi-rows

            $res = [];
            if(preg_match_all(
                '!\[\[row\]\]\s*\[\[key\]\](.+?)\[\[/key\]\]\s*\[\[translate\]\](.+?)\[\[/translate\]\]\s*\[\[/row\]\]!usi',
                $content,
                $match
            )) {
                foreach($match[1] as $i => $_) {
                    $orig = trim($match[1][$i]);
                    $trans = trim($match[2][$i]);
                    if(isset($res[ md5($orig) ])) {
                        ErrHandler::log("Duplicate translation for [$orig]");
                    }
                    $res[ md5($orig) ] = $trans;
                }
                $content = preg_replace('!\[\[row\]\].+?\[\[/row\]\]!usi', "\n", $content);
            }

            // Single-rows

            $content = preg_split("!\n!ui", $content);

            $tmp = [];
            foreach($content as $row) {
                $row = trim($row);
                if(!$row || preg_match('!^#!', $row)) {
                    continue;
                }
                $tmp[] = $row;
            }
            $content = $tmp;
            unset($tmp);

            $len = count($content);
            for($i = 0; $i < $len; $i++) {
                if(!($i % 2)) {
                    if(isset($res[ md5($content[$i]) ])) {
                        ErrHandler::log("Duplicate translation for [" . $content[$i] . "]");
                    }
                    $res[ md5($content[$i]) ] = $content[$i + 1];
                }
            }

            if(!$res) {
                throw new Err("Result translate data is empty");
            }
            if(!file_put_contents($cacheFile, "<?php\nreturn ".var_export($res, 1).";\n")) {
                throw new Err("Failed to save translate res data to [$cacheFile]");
            }
        }
    }

    static function translate(string $keyStr, string $lang = null): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!$lang) {
            $lang = static::$_langDefault;
        }
        else {
            static::validateLang($lang);
        }

        static $translates;
        if (empty($translates[$lang])) {
            $cacheFile = static::$_cacheDir. '/' . $lang;
            $translates[$lang] = require_once($cacheFile);
            if(!$translates[$lang]) {
                throw new Err("Translate data is empty from [$cacheFile]");
            }
        }

        $key = md5(trim($keyStr));
        if (empty($translates[$lang][$key])) {
            ErrHandler::log("No translate for [$keyStr] lang [$lang]");
            return $keyStr;
        }

        return $translates[$lang][$key];
    }

    static function translateMacro($keyStr)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $res = Language::translate($keyStr);
        $args = func_get_args();
        if (count($args) > 1) {
            array_shift($args);
            foreach($args as $arg) {
                $res = preg_replace('!%s!u', $arg, $res, $limit = 1);
            }
        }
        return $res;
    }
}