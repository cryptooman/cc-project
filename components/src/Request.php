<?php
/**
 * Usage:
 *      Request::init( ... );
 *      Request::collectInfo();
 *      [ Request::setUidCookie( ... ); ]
 *      [ Request::setLanguage( ... ); ]
 *      $ip = Request::ip();
 *      $browser = Request::browser();
 *      ...
 */
class Request
{
    const BROWSER_CHROME        = 'chrome';
    const BROWSER_FFOX          = 'ffox';
    const BROWSER_OPERA         = 'opera';
    const BROWSER_SAFARI        = 'safari';
    const BROWSER_EDGE          = 'edge';
    const BROWSER_MSIE          = 'msie';
    const BROWSER_IOS_MOBILE    = 'ios_mobile';
    const BROWSER_ANDROID       = 'android';
    const BROWSER_BOT           = 'bot';
    const BROWSER_UNKNOWN       = 'unknown';

    protected static $_request = [
        'url'           => '',
        'queryStr'      => '',
        'ip'            => '',
        'referrer'      => '',
        'host'          => '',
        'browser'       => '',
        'browserStr'    => '',
        'uid'           => '',
        'lang'          => '',
        'isMobile'      => false,
        'isGet'         => false,
        'isPost'        => false,
        'isHttps'       => false,
        'isAjax'        => false,
    ];
    protected static $_inited;

    static function init()
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }
    }

    static function collectInfo()
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (empty($_SERVER)) {
            throw new Err("Server globals are empty");
        }

        // Request url
        if ($v = static::_getServerValue('REDIRECT_URL')) {
            static::$_request['url'] = $v;
            if ($v = static::_getServerValue('REDIRECT_QUERY_STRING')) {
                static::$_request['url'] .= '?' . $v;
            }
        }
        elseif ($v = static::_getServerValue('REQUEST_URI')) {
            static::$_request['url'] = $v;
        }
        // Request url must be at least '/'
        if (!static::$_request['url']) {
            throw new Err("Empty request url");
        }

        // Request query string
        if ($v = static::_getServerValue('REDIRECT_QUERY_STRING')) {
            static::$_request['queryStr'] = $v;
        }
        elseif ($v = static::_getServerValue('QUERY_STRING')) {
            static::$_request['queryStr'] = $v;
        }

        // IP
        if ($v = static::_getServerValue('HTTP_X_FORWARDED_FOR')) {
            static::$_request['ip'] = $v;
        }
        if (!static::$_request['ip'] && $v = static::_getServerValue('REMOTE_ADDR')) {
            static::$_request['ip'] = $v;
        }
        if (!static::$_request['ip']) {
            throw new Err("Empty requester IP address");
        }

        // Referrer
        if ($v = static::_getServerValue('HTTP_REFERER')) {
            static::$_request['referrer'] = $v;
        }

        // Host
        if ($v = static::_getServerValue('HTTP_HOST')) {
            static::$_request['host'] = $v;
        }
        if (!static::$_request['host']) {
            throw new Err("Empty request host");
        }

        // Browser
        if ($v = static::_getServerValue('HTTP_USER_AGENT')) {
            static::$_request['browserStr'] = $v;
        }
        list(static::$_request['browser'], static::$_request['isMobile']) = static::_detectBrowser(static::$_request['browserStr']);

        // Request method GET
        if (($v = static::_getServerValue('REQUEST_METHOD')) && $v == 'GET') {
            static::$_request['isGet'] = true;
        }

        // Request method POST
        if (($v = static::_getServerValue('REQUEST_METHOD')) && $v == 'POST') {
            static::$_request['isPost'] = true;
        }

        // Proto HTTPS
        if (($v = static::_getServerValue('HTTP_X_FORWARDED_PROTO')) && $v == 'https') {
            static::$_request['isHttps'] = true;
        }
        if (!static::$_request['isHttps'] && ($v = static::_getServerValue('REQUEST_SCHEME')) && $v == 'https') {
            static::$_request['isHttps'] = true;
        }

        // Is ajax
        if (($v = static::_getServerValue('HTTP_X_REQUESTED_WITH')) && $v == 'XMLHttpRequest') {
            static::$_request['isAjax'] = true;
        }
    }

    // $hostToLangMap = ['mysite.com' => 'en', 'ru.mysite.com' => 'ru', ...]
    static function setLanguage(array $hostToLangMap)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_request['host']) {
            throw new Err("Empty request host");
        }
        $requestHost = static::_get('host');

        if (!$hostToLangMap) {
            throw new Err("Empty host-to-lang map");
        }

        if (!isset($hostToLangMap[$requestHost])) {
            throw new Err("Failed to detect language: Request host [$requestHost]");
        }
        $lang = $hostToLangMap[$requestHost];

        Language::validateLang($lang);
        static::$_request['lang'] = $lang;
    }

    static function setUidCookie(string $uidCookieName = 'uid')
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $uid = Cookie::get($uidCookieName);
        $uid = Vars::filter($uid, Vars::REGX, '!^[a-f0-9]{32}$!', null, '');
        if (!$uid) {
            Cookie::set($uidCookieName, Rand::hash(32), Cookie::EXPIRE_NEVER);
        }
        static::$_request['uid'] = $uid;
    }

    static function url(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('url');
    }

    static function queryStr(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('queryStr');
    }

    static function ip(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('ip');
    }

    static function referrer(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('referrer');
    }

    static function host(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('host');
    }

    static function browser(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('browser');
    }

    static function browserStr(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('browserStr');
    }

    static function uid(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('uid');
    }

    static function lang(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('lang');
    }

    static function isMobile(): bool
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('isMobile');
    }

    static function isGet(): bool
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('isGet');
    }

    static function isPost(): bool
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('isPost');
    }

    static function isHttps(): bool
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('isHttps');
    }

    static function isAjax(): bool
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::_get('isAjax');
    }

    static function dump(): array
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::$_request;
    }

    protected static function _get(string $name)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!isset(static::$_request[$name])) {
            throw new Err("Unknown request info [$name]");
        }
        return static::$_request[$name];
    }

    // NOTE: If server cache is enabled, then mobile browser detection and redirect are done at server level
    protected static function _detectBrowser(string $browserStr): array
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $browser = '';
        $isMobile = false;

        if (!$browserStr) {
            return [$browser, $isMobile];
        }

        // Mobile
        if (preg_match('!\bMOBILE\b!i', $browserStr)) {
            $isMobile = true;
        }
        foreach ([
            '!\bIPHONE\b!i'         => self::BROWSER_IOS_MOBILE,
            '!\bIPAD\b!i'           => self::BROWSER_IOS_MOBILE,
            '!\bANDROID\b!i'        => self::BROWSER_ANDROID,
            '!\bOPERA.+?MINI\b!i'   => 'opera mini',
            '!\bOPERA.+?MOBI\b!i'   => 'opera mobi',
            '!\bIEMOBILE\b!i'       => 'iemobile',
            '!\bBLACKBERRY\b!i'     => 'blackberry',
            '!\bSYMBIAN\b!i'        => 'symbian',
        ] as $regx => $bro) {
            if (preg_match($regx, $browserStr)) {
                $browser = $bro;
                $isMobile = true;
                break;
            }
        }

        // Desktop
        if (!$browser) {
            foreach ([
                '!\bOPERA\b!i'      => self::BROWSER_OPERA,
                '!\bMSIE\b!i'       => self::BROWSER_MSIE,
                '!\bEDGE\b!i'       => self::BROWSER_EDGE,
                '!\bFIREFOX\b!i'    => self::BROWSER_FFOX,
                '!\bCHROME\b!i'     => self::BROWSER_CHROME,
                '!\bSAFARI\b!i'              => self::BROWSER_SAFARI,
                '!MACINTOSH.+?APPLEWEBKIT!i' => self::BROWSER_SAFARI,
            ] as $regx => $bro) {
                if (preg_match($regx, $browserStr)) {
                    $browser = $bro;
                    break;
                }
            }
        }

        // Bots
        if (!$browser) {
            foreach ([
                '!\bCURL\b!i' => self::BROWSER_BOT,
                '!\bWGETL\b!i' => self::BROWSER_BOT,
                '!\bBOT\b!i' => self::BROWSER_BOT,
            ] as $regx => $bro) {
                if (preg_match($regx, $browserStr)) {
                    $browser = $bro;
                    break;
                }
            }
        }

        if (!$browser) {
            $browser = self::BROWSER_UNKNOWN;
        }

        return [$browser, $isMobile];
    }

    static protected function _getServerValue(string $name): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (empty($_SERVER[$name])) {
            return '';
        }
        return trim($_SERVER[$name]);
    }
}
