<?php
/**
 * Usage:
 *      Curl::init( ... );
 *      $curl = new Curl;
 *      $curl->setRequest('http://some-url');
 *      $res = $curl->doRequest();
 */
class Curl
{
    const HTTP_METHOD_GET       = 'GET';
    const HTTP_METHOD_POST      = 'POST';
    const HTTP_METHOD_PUT       = 'PUT';
    const HTTP_METHOD_DELETE    = 'DELETE';
    const HTTP_METHOD_HEAD      = 'HEAD';

    const URL_MAX_LENGTH                = 2048;
    const POST_DATA_MAX_BYTES           = 10485760; // 10 Mb
    const RESPONSE_BODY_MAX_BYTES       = 10485760; // 10 Mb
    const REQUEST_ATTEMPTS              = 3;
    const REQUEST_ATTEMPT_DELAY_BASE_MS = 1000;

    protected static $_curlOptsDefault = [
        CURLOPT_VERBOSE             => 0,
        CURLOPT_CONNECTTIMEOUT      => 20,
        CURLOPT_TIMEOUT             => 60,
        CURLOPT_FAILONERROR         => 0,
        CURLOPT_RETURNTRANSFER      => 1,
        CURLOPT_FOLLOWLOCATION      => 1,
        CURLOPT_MAXREDIRS           => 5,
        CURLOPT_NOBODY              => 0,
        CURLOPT_HEADER              => 1,
        CURLOPT_ENCODING            => 'gzip',
        CURLOPT_BUFFERSIZE          => 16384,
        CURLOPT_MAXCONNECTS         => 5,
        CURLOPT_PORT                => 80,
        CURLOPT_SSL_VERIFYPEER      => 0,
        CURLOPT_SSL_VERIFYHOST      => 0,
        CURLOPT_HTTPHEADER          => [],
        CURLOPT_POSTFIELDS          => '',
        CURLOPT_INTERFACE           => null,
    ];
    protected static $_lastRequest = [];
    protected static $_lastResponse = [];
    protected static $_inited;

    protected $_curlOptions = [];
    protected $_curlHandler;
    protected $_requestUrl;

    static function init(array $_curlOptsDefault = [])
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        foreach ($_curlOptsDefault as $name => $value) {
            if ($value !== null) {
                static::$_curlOptsDefault[$name] = $value;
            }
        }
    }

    static function getLastRequest()
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::$_lastRequest;
    }

    static function getLastResponse()
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        return static::$_lastResponse;
    }

    function __construct() {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        // Reset $last* data in case of new curl object created
        static::$_lastRequest = [];
        static::$_lastResponse = [];

        if (Verbose::getLevel() >= Verbose::LEVEL_3) {
            static::$_curlOptsDefault[CURLOPT_VERBOSE] = 1;
            static::$_curlOptsDefault[CURLOPT_NOPROGRESS] = 0;
        }
    }

    function __destruct()
    {
        if (is_resource($this->_curlHandler)) {
            @curl_close($this->_curlHandler);
        }
    }

    function setRequest(
        string $url, string $method = self::HTTP_METHOD_GET, array $headers = [], string $postData = '', array $curlOpts = []
    ): Curl
    {
        static::$_lastRequest = [];

        if (!$url || strlen($url) > self::URL_MAX_LENGTH) {
            throw new CurlException('Bad request url', CurlException::E_BAD_REQUEST_URL, $url, null, $this->getRequestAsBashCmd());
        }
        $this->_requestUrl = $url;

        if (!in_array($method, [
            self::HTTP_METHOD_GET, self::HTTP_METHOD_POST, self::HTTP_METHOD_PUT, self::HTTP_METHOD_DELETE, self::HTTP_METHOD_HEAD
        ])) {
            throw new CurlException(
                "Request method [$method] is not supported", CurlException::E_BAD_HTTP_METHOD, $url, null, $this->getRequestAsBashCmd()
            );
        }

        if ($headers && $headers == array_values($headers)) {
            throw new CurlException(
                "Headers must be in associative format", CurlException::E_BAD_HEADERS, $url, null, $this->getRequestAsBashCmd()
            );
        }

        if ($postData && strlen($postData) > self::POST_DATA_MAX_BYTES) {
            throw new CurlException(
                "Bad post data (too long): " . substr($postData, 0, 2048), CurlException::E_BAD_POST_DATA, $url, null, $this->getRequestAsBashCmd()
            );
        }

        if ($curlOpts && $curlOpts == array_values($curlOpts)) {
            throw new CurlException(
                "Curl options must be in associative format", CurlException::E_BAD_CURL_OPTIONS, $url, null, $this->getRequestAsBashCmd()
            );
        }

        $this->_curlHandler = curl_init();
        if (!is_resource($this->_curlHandler)) {
            throw new CurlException(
                'Failed to init curl resource', CurlException::E_FAILED_CURL_INIT, $url, $this->_curlHandler, $this->getRequestAsBashCmd()
            );
        }

        $this->_curlOptions = static::$_curlOptsDefault;

        $this->_curlOptions[CURLOPT_URL] = $url;
        $this->_curlOptions[CURLOPT_CUSTOMREQUEST] = $method;

        $headersAsStr = [];
        if ($headers) {
            foreach ($headers as $name => $value) {
                $headersAsStr[] = "$name: $value";
            }
            $this->_curlOptions[CURLOPT_HTTPHEADER] = $headersAsStr;
        }

        if (strlen($postData)) {
            if (!in_array($method, [self::HTTP_METHOD_POST, self::HTTP_METHOD_PUT])) {
                throw new CurlException(
                    "Bad request method [$method] for post data", CurlException::E_BAD_HTTP_METHOD, $url, null, $this->getRequestAsBashCmd()
                );
            }
            $this->_curlOptions[CURLOPT_POSTFIELDS] = $postData;
        }

        if ($curlOpts) {
            $this->_curlOptions = array_replace($this->_curlOptions, $curlOpts);
        }

        $tmp = [];
        foreach ($this->_curlOptions as $opt => $val) {
            if ($val !== null) {
                $tmp[$opt] = $val;
            }
        }
        $this->_curlOptions = $tmp;
        unset($tmp);

        if (!curl_setopt_array($this->_curlHandler, $this->_curlOptions)) {
            throw new CurlException(
                "Failed to set curl options", CurlException::E_FAILED_SET_CURL_OPTIONS, $url, $this->_curlHandler, $this->getRequestAsBashCmd()
            );
        }

        static::$_lastRequest = $this->getRequest();

        return $this;
    }

    function doRequest(): array
    {
        static::$_lastResponse = [];

        if (!$this->_curlHandler) {
            throw new CurlException(
                'Undefined request', CurlException::E_UNDEFINED_REQUEST, $this->_requestUrl, $this->_curlHandler, $this->getRequestAsBashCmd()
            );
        }

        Verbose::echo1('Doing request: ' . $this->getRequestAsBashCmd());

        $response = [];
        $attempts = self::REQUEST_ATTEMPTS;
        $i = 0;
        while ($attempts--) {
            $response = curl_exec($this->_curlHandler);
            if ($response) {
                $response = static::$_lastResponse = $this->_parseResponse($response);
                if ($response) {
                    break;
                }
            }
            if ($attempts) {
                usleep(self::REQUEST_ATTEMPT_DELAY_BASE_MS * 1000 * pow(2, $i++));
            }
        }

        if (!$response) {
            throw new CurlException(
                'Bad response', CurlException::E_FAILED_GET_RESPONSE, $this->_requestUrl, $this->_curlHandler, $this->getRequestAsBashCmd()
            );
        }

        return $response;
    }

    function getRequest(): array
    {
        return [
            'url'       => $this->_curlOptions[CURLOPT_URL],
            'method'    => $this->_curlOptions[CURLOPT_CUSTOMREQUEST],
            'headers'   => $this->_curlOptions[CURLOPT_HTTPHEADER],
            'postData'  => $this->_curlOptions[CURLOPT_POSTFIELDS],
        ];
    }

    function getRequestAsBashCmd(): string
    {
        $cmd = ['curl'];

        if ($this->_curlOptions[CURLOPT_VERBOSE]) {
            $cmd[] = '--verbose';
        }

        $cmd[] = '--connect-timeout ' . $this->_curlOptions[CURLOPT_CONNECTTIMEOUT];
        $cmd[] = '--max-time ' . $this->_curlOptions[CURLOPT_TIMEOUT];
        $cmd[] = '--max-redirs ' . $this->_curlOptions[CURLOPT_MAXREDIRS];

        if (self::REQUEST_ATTEMPTS) {
            $cmd[] = '--retry ' . (self::REQUEST_ATTEMPTS - 1);
        }
        if (!empty($this->_curlOptions[CURLOPT_INTERFACE])) {
            $cmd[] = '--interface ' . escapeshellarg($this->_curlOptions[CURLOPT_INTERFACE]);
        }
        if ($this->_curlOptions[CURLOPT_FOLLOWLOCATION]) {
            $cmd[] = '--location';
        }
        if ($this->_curlOptions[CURLOPT_NOBODY]) {
            $cmd[] = '--head';
        }
        if ($this->_curlOptions[CURLOPT_HEADER]) {
            $cmd[] = '--include';
        }
        if (!$this->_curlOptions[CURLOPT_SSL_VERIFYPEER] || !$this->_curlOptions[CURLOPT_SSL_VERIFYHOST]) {
            $cmd[] = '--insecure';
        }

        $cmd[] = '-X ' . $this->_curlOptions[CURLOPT_CUSTOMREQUEST];

        if ($this->_curlOptions[CURLOPT_POSTFIELDS]) {
            $cmd[] = '-d ' . escapeshellarg($this->_curlOptions[CURLOPT_POSTFIELDS]);
        }

        if ($this->_curlOptions[CURLOPT_HTTPHEADER]) {
            $headers = [];
            foreach ($this->_curlOptions[CURLOPT_HTTPHEADER] as $header) {
                $headers[] = '-H ' . escapeshellarg($header);
            }
            $cmd[] = join(' ', $headers);
        }

        $cmd[] = escapeshellarg($this->_curlOptions[CURLOPT_URL]);

        return join(' ', $cmd);
    }

    protected function _parseResponse(string $response): array
    {
        $resCode = 0;
        $resHeaders = [];

        $headerSectionClose = "\r\n\r\n";
        $index = -1;
        $offset = 0;

        while (($pos = strpos($response, $headerSectionClose, $offset)) !== false) {
            $index++;

            $header = substr($response, $offset, $pos - $offset);
            $offset = $pos + strlen($headerSectionClose);
            if (!preg_match('!^(?<proto>HTTP/[\d\.]+\s(?<code>\d{3}).*)!', $header, $match)) {
                break;
            }

            $resCode = $match['code']; // Last response headers is used
            $resHeaders[$index]['_'] = $match['proto'];

            if (preg_match_all("!^(.+?):(.*)!m", $header, $match)) {
                foreach ($match[1] as $i => $_) {
                    $name = $match[1][$i];
                    $resHeaders[$index][$name] = $match[2][$i];
                }
            }
        }

        if (!$resCode) {
            throw new CurlException(
                'Failed to get response code',
                CurlException::E_FAILED_PARSE_RESPONSE, $this->_requestUrl, $this->_curlHandler, $this->getRequestAsBashCmd()
            );
        }

        $resHeaders = array_reverse($resHeaders); // Last response headers goes first

        $resBody = substr($response, $offset);
        $resBodyLen = strlen($resBody);
        if ($resBodyLen > self::RESPONSE_BODY_MAX_BYTES) {
            throw new CurlException(
                "Response body is too big [$resBodyLen]", CurlException::E_BAD_RESPONSE_BODY, $this->_requestUrl, null, $this->getRequestAsBashCmd()
            );
        }

        return [
            'code'    => $resCode,
            'headers' => $resHeaders,
            'body'    => $resBody
        ];
    }
}