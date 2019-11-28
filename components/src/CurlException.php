<?php
/**
 *
 */
class CurlException extends \Exception
{
    const E_DEFAULT                     = 10000;
    const E_BAD_REQUEST_URL             = 10001;
    const E_BAD_HTTP_METHOD             = 10002;
    const E_BAD_HEADERS                 = 10003;
    const E_BAD_POST_DATA               = 10004;
    const E_BAD_CURL_OPTIONS            = 10005;
    const E_FAILED_CURL_INIT            = 10006;
    const E_FAILED_SET_CURL_OPTIONS     = 10007;
    const E_UNDEFINED_REQUEST           = 10008;
    const E_FAILED_GET_RESPONSE         = 10009;
    const E_FAILED_PARSE_RESPONSE       = 10010;
    const E_BAD_RESPONSE_BODY           = 10011;

    function __construct(string $message, int $code = self::E_DEFAULT, string $requestUrl = '', $curlHandler = null, string $reqAsBashCmd = '')
    {
        if (is_resource($curlHandler)) {
            if ($curlMsg = curl_error($curlHandler)) {
                $message = $message . " ($curlMsg)";
            }
            if ($curlCode = curl_errno($curlHandler)) {
                $code = $curlCode; // Overwrite class code with curl lib code
            }
        }
        if ($requestUrl) {
            $message = $message . PHP_EOL . 'Request URL: ' . $requestUrl;
        }
        if ($reqAsBashCmd) {
            $message = $message . PHP_EOL . 'Request as bash cmd: ' . $reqAsBashCmd;
        }
        parent::__construct($message, $code);
    }
}