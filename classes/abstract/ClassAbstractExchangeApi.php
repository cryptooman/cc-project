<?php
/**
 *
 */
abstract class ClassAbstractExchangeApi
{
    protected static $_curlInterfaceIps = [];

    static function getApi(int $exchangeId): ClassAbstractExchangeApi
    {
        $exchangesApi = [
            ModelExchanges::BITFINEX_ID => 'ClassExchangeApiBitfinex',
        ];
        if (!isset($exchangesApi[$exchangeId])) {
            throw new Err("Bad exchange api [$exchangeId]");
        }
        return (new $exchangesApi[$exchangeId]);
    }

    function __construct()
    {
        $interfaceIps = Config::get('admin.exchangesRequests.curlInterfaceIps');
        if ($interfaceIps) {
            $this->_setCurlInterfaceIps($interfaceIps);
        }
    }

    abstract function doPublicRequest(string $url, string $method = Curl::HTTP_METHOD_GET, array $headers = [], array $data = []): array;

    abstract function doAuthedRequest(string $apiKey, string $apiSecret, string $url, string $method, array $headers, array $data): array;

    protected function _doRequest(string $url, string $method, array $headers, array $data): array
    {
        $postData = '';
        if ($data) {
            $postData = http_build_query($data);
            if (!$postData) {
                throw new Err("Failed to build post data query: ", $data);
            }
        }

        $curl = new Curl;
        $curlOpts = [];
        if ($interfaceIp = $this->_getCurlInterfaceIp()) {
            $curlOpts[CURLOPT_INTERFACE] = $interfaceIp;
        }
        $curl->setRequest($url, $method, $headers, $postData, $curlOpts);

        $response = [];
        $attempts = 7; // Max sleep is 32 sec
        $i = 0;
        while ($attempts--) {
            $response = $curl->doRequest();
            if (!$response) {
                throw new Err("Response is empty for request: ", func_get_args());
            }
            if ($response['code'] >= 500 && $response['code'] <= 599) {
                $sleep = pow(2, $i++);
                Verbose::echo2("Response code [%s]: Attempts left [$attempts]: Sleep [$sleep] sec and retry to request", $response['code']);
                sleep($sleep);
                continue;
            }
            break;
        }
        if ($interfaceIp) {
            $response['interfaceIp'] = $interfaceIp;
        }

        return $response;
    }

    protected function _setCurlInterfaceIps(array $interfaceIps, bool $reset = false)
    {
        if (!static::$_curlInterfaceIps || $reset) {
            shuffle($interfaceIps['list']);
            static::$_curlInterfaceIps['list'] = $interfaceIps['list'];
            static::$_curlInterfaceIps['index'] = 0;
            static::$_curlInterfaceIps['indexMax'] = count($interfaceIps['list']) - 1;
            static::$_curlInterfaceIps['resetRand'] = $interfaceIps['resetRand'];
        }
    }

    protected function _getCurlInterfaceIp(): string
    {
        if (!static::$_curlInterfaceIps) {
            return '';
        }
        if (mt_rand(1, static::$_curlInterfaceIps['resetRand']) == 1) {
            $this->_setCurlInterfaceIps(Config::get('admin.exchangesRequests.curlInterfaceIps'), true);
            // In case of last used interface will be first in the queue
            sleep(1);
        }
        $ci = &static::$_curlInterfaceIps;
        $interface = $ci['list'][$ci['index']];
        if (!$interface) {
            throw new Err("Empty curl interface: index [%s] list: ", $ci['index'], $ci['list']);
        }
        $ci['index']++;
        if ($ci['index'] > $ci['indexMax']) {
            $ci['index'] = 0;
        }
        return $interface;
    }
}