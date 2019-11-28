<?php
/**
 *
 */
class Str
{
    static function quote(string $str): string
    {
        return htmlentities($str, ENT_QUOTES, "utf-8", false);
    }

    static function unquote(string $str): string
    {
        $str = str_replace("&apos;", "'", $str);
        return html_entity_decode($str, ENT_QUOTES, "utf-8");
    }

    static function quoteTags(string $str): string
    {
        return str_replace(array('<','>'), array('&lt;','&gt;'), $str);
    }

    static function unquoteTags(string $str): string
    {
        return str_replace(array('&lt;','&gt;'), array('<','>'), $str);
    }

    static function unquoteStripTags(string $str): string
    {
        return strip_tags(static::unquote($str));
    }

    // Adds tail slash to string path
    static function endSlash(string $str): string
    {
        return (preg_match('!/$!', $str)) ? $str : $str . '/';
    }

    // Removes tail slash from string path
    static function rmEndSlash(string $str): string
    {
        if (!preg_match('!/$!', $str)) {
            return $str;
        }
        return preg_replace('!/+$!', '', $str);
    }

    static function fixSpaces(string $str): string
    {
        return trim(preg_replace('!\s+!us', ' ', $str));
    }

    static function toLine(string $str): string
    {
        return preg_replace('![\r\n\v]!us', ' ', $str);
    }

    static function cutAddDots(string $str, int $len = 255, $dots = '..'): string
    {
        if (strlen($str) > $len) {
            $getLen = $len - strlen($dots);
            if ($getLen <= 0) {
                throw new Err("Cut length [$len] is too small");
            }
            $str = substr($str, 0, $getLen) . $dots;
        }
        return $str;
    }

    static function splitToWords(string $str): array
    {
        return preg_split('![^\p{L}]!usi', $str, -1, PREG_SPLIT_NO_EMPTY);
    }

    // NOTE: All "\s" converting to " "
    static function trimWords(string $str, int $len): string
    {
        $words = preg_split('![\s]!usi', $str);
        $res = '';
        foreach($words as $word)
        {
            if(mb_strlen($res) + mb_strlen($word) <= $len)
                $res .= $word;
            else
                break;

            if(mb_strlen($res) + mb_strlen(' ') <= $len - 1)
                $res .= ' ';
            else
                break;
        }
        return trim($res);
    }

    static function mbUcFirst(string $str): string
    {
        return mb_strtoupper(mb_substr($str, 0, 1)).mb_substr($str, 1);
    }

    static function mbLcFirst(string $str): string
    {
        return mb_strtolower(mb_substr($str, 0, 1)).mb_substr($str, 1);
    }

    static function mbUcWords(string $str): string
    {
        return join(" ", array_map('mb_ucfirst', explode(" ", $str)) );
    }

    // NOTE: $cut is always true
    static function mbWordWrap(string $str, int $len, string $delimiter = "\n", bool $cut = true): string
    {
        return preg_replace('!([^\s]{'.$len.'})!usi', '$1'.$delimiter, $str);
    }

    static function mbReverse(string $str): string
    {
        preg_match_all('/./us', $str, $arr);
        return join('', array_reverse($arr[0]));
    }

    static function translit(
        string $str, bool $tolower = false, string $delimiter = '-', bool $trim_delimiter = false, bool $remove_delimiter_duplicates = false
    ): string
    {
        $translit = array(
            // Common
            'º'=>'','ª'=>'',
            // Ru
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'xh','ь'=>'','ы'=>'y','ъ'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'YO','Ж'=>'ZH','З'=>'Z','И'=>'I','Й'=>'Y',
            'К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F',
            'Ч'=>'H','Ц'=>'C','Ч'=>'CH','Ш'=>'SH','Щ'=>'XH','Ь'=>'','Ы'=>'Y','Ъ'=>'','Э'=>'E','Ю'=>'YU','Я'=>'YA',
            // De
            'ä'=>'a','ö'=>'o','ü'=>'u','ß'=>'s',
            // It
            'à'=>'a','á'=>'a','è'=>'e','é'=>'e','ì'=>'i','î'=>'i','í'=>'i','ò'=>'o','ó'=>'o','ù'=>'u','ú'=>'u',
            // Fr
            'à'=>'a','â'=>'a','ä'=>'a','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','œ'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ÿ'=>'y','ç'=>'c','æ'=>'a','₣'=>'',
            // Es
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u','¡'=>'','¿'=>'','₧'=>'',
        );

        if(!$str && $str !== 0)
            return '';

        $res = preg_replace('![^a-zA-Z0-9'.preg_quote($delimiter).']!u', $delimiter, strtr($str, $translit));

        if($tolower)
            $res = strtolower($res);

        if($trim_delimiter)
        {
            $res = preg_replace('!^'.preg_quote($delimiter).'+!', '', $res);
            $res = preg_replace('!'.preg_quote($delimiter).'+$!', '', $res);
        }

        if($remove_delimiter_duplicates)
            $res = preg_replace('!'.preg_quote($delimiter).'+!', $delimiter, $res);

        if(!$res && $res !== 0)
            throw new Err("Empty transliterated result");

        return $res;
    }

    static function stripSlang(string $str, $replacement = '...'): string
    {
        // Ru bad words
        $slang = array(
            "/[хx]у[йеeёя]/usi",
            "/(?<![ншр])[еeё]б[аayу][лн]?/usi",
            "/(?<![нрд])[еeё]бл[оoяиа]/usi",
            "/пид[oо]?[рp]/usi",
            "/(?<!ски)пид[aа][рp]/usi",
            "/пизд/usi"
        );
        foreach($slang as $s)
            $str = preg_replace($s, $replacement, $str);

        return $str;
    }

    static function safeHtml(string $str): string
    {
        $regx = array(
            array('!scri?pt!usi',			's&#99ript'),	// script
            array('!on([a-z]+?\s*=)!usi', 	'&#110n\\1'),	// onclick, onload, ...
            array('!embed!usi', 			'e&#109bed'),
            array('!flash!usi', 			'f&#108ash'),
            array('!object!usi', 			'o&#98ject'),
            array('!applet!usi', 			'app&#108et'),
            array('!stlye!usi', 			's&#116lye'),
            array('!form!usi', 				'f&#111rm'),
            array('!meta!usi', 				'm&#101ta'),
            array('!frame!usi',	 			'f&#114ame'),
            array('!\$!usi', 				'&#36'),	// jquery: $(), $.
            array('!\?!usi', 				'&#63'),	// <?, <?php, <?xml
            array('!\(!usi', 				'&#40'),
        );
        foreach($regx as $val)
        {
            $str = preg_replace($val[0], $val[1], $str);
        }
        return $str;
    }

    // Orig taken from http://icodesnip.com/snippet/php/close-unclosed-tags
    static function closeHtmlTag(string $text): string
    {
        $patt_open    	= "%((?<!</)(?<=<)[\s]*[^/!>\s]+(?=>|[\s]+[^>]*[^/]>)(?!/>))%";
        $patt_close    	= "%((?<=</)([^>]+)(?=>))%";
        $skip_single	= array('area','base','basefont','col','br','frame','hr','img','input','link','meta','param');
        if(preg_match_all($patt_open,$text,$matches))
        {
            $m_open = $matches[1];
            if(!empty($m_open))
            {
                preg_match_all($patt_close,$text,$matches2);
                $m_close = $matches2[1];
                if(count($m_open) > count($m_close))
                {
                    $m_open = array_reverse($m_open);
                    foreach($m_close as $tag) {
                        if(!isset($c_tags[$tag])) $c_tags[$tag] = 0;
                        $c_tags[$tag]++;
                    }
                    foreach($m_open as $k => $tag)
                    {
                        if(!in_array($tag, $skip_single))
                        {
                            if(!isset($c_tags[$tag])) $c_tags[$tag] = 0;
                            if($c_tags[$tag]-- <= 0) $text.='</'.$tag.'>';
                        }
                    }
                }
            }
        }

        return $text;
    }
}