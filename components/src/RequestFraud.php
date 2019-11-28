<?php
/**
 * Usage:
 *      RequestFraud::init( ... );
 *      In controller
 *          if (!RequestFraud::isFraud( ... )) {
 *              ... bounce or reject
 *          }
 *      In html <header>
 *          RequestFraud::renderSetComputedCookieJs();
 */
class RequestFraud
{
    const COOKIE_NAME_SECRET_PHP = '93e3106362f7'; // Being calculated at php level
    const COOKIE_NAME_COMPUTE_JS = '81b9f9c05070'; // Being calculated at js level

    protected static $_cookieDomain;
    protected static $_secretCookie = [];
    protected static $_inited;

    static function init(string $cookieDomain)
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        static::$_cookieDomain = $cookieDomain;
    }

    static function setSecretCookie()
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $secretCookie = Cookie::get(self::COOKIE_NAME_SECRET_PHP);
        if (!$secretCookie) {
            $secretCookie = [Rand::hash(32), Rand::hash(32)]; // [key, pass]
            Cookie::set(
                self::COOKIE_NAME_SECRET_PHP, Base64::encode(Json::encode($secretCookie)), Cookie::EXPIRE_SESSION, static::$_cookieDomain
            );
        }
        static::$_secretCookie = $secretCookie;
    }

    static function isFraud()
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!Request::browser() || Request::browser() == Request::BROWSER_BOT) {
            return true;
        }

        $computedCookie = Cookie::get(self::COOKIE_NAME_COMPUTE_JS);
        if(!$computedCookie) {
            return true;
        }

        $computedCookie = static::_antiFraudCryptoJsAesDecrypt(static::$_secretCookie[1], $computedCookie);
        if(!$computedCookie || $computedCookie != static::$_secretCookie[0]) {
            return true;
        }

        return false;
    }

    static function getSecretCookie(): array
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::$_secretCookie;
    }

    static function renderSetComputedCookieJs(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $html = '';
        $html .= '<script type="text/javascript">';
        $html .= 'if(!getCookie("' . self::COOKIE_NAME_COMPUTE_JS . '")) {';
        $html .= 'setCookie("' . self::COOKIE_NAME_COMPUTE_JS . '", CryptoJS.AES.encrypt(JSON.stringify("' . static::$_secretCookie[0] . '"), "' . static::$_secretCookie[1] . '", {format: CryptoJSAesJson}).toString(), 0, "' . static::$_cookieDomain . '");';
        $html .= '}';
        $html .= '</script>';

        return $html;
    }

    // Decrypt data from a CryptoJS json encoding string (taken from 3rd-party source)
    protected static function _antiFraudCryptoJsAesDecrypt(string $passphrase, string $jsonString): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $jsondata = json_decode($jsonString, true);
        $salt = hex2bin($jsondata["s"]);
        $ct = base64_decode($jsondata["ct"]);
        $iv  = hex2bin($jsondata["iv"]);
        $concatedPassphrase = $passphrase.$salt;
        $md5 = array();
        $md5[0] = md5($concatedPassphrase, true);
        $result = $md5[0];
        for ($i = 1; $i < 3; $i++) {
            $md5[$i] = md5($md5[$i - 1].$concatedPassphrase, true);
            $result .= $md5[$i];
        }
        $key = substr($result, 0, 32);
        $data = openssl_decrypt($ct, 'aes-256-cbc', $key, true, $iv);
        return json_decode($data, true);
    }

    // Encrypt value to a cryptojs compatiable json encoding string (taken from 3rd-party source)
    protected static function _antiFraudCryptoJsAesEncrypt(string $passphrase, string $value): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $salt = openssl_random_pseudo_bytes(8);
        $salted = '';
        $dx = '';
        while (strlen($salted) < 48) {
            $dx = md5($dx.$passphrase.$salt, true);
            $salted .= $dx;
        }
        $key = substr($salted, 0, 32);
        $iv  = substr($salted, 32,16);
        $encrypted_data = openssl_encrypt(json_encode($value), 'aes-256-cbc', $key, true, $iv);
        $data = array("ct" => base64_encode($encrypted_data), "iv" => bin2hex($iv), "s" => bin2hex($salt));
        return json_encode($data);
    }
}