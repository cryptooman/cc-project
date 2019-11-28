<?php
/**
 * Usage:
 *      $res = Response::json(...);
 *      Response::writeContentTypeHeader();
 *      echo $res;
 */
class Response
{
    const TYPE_PLAIN    = "plain";
    const TYPE_HTML 	= "html";
    const TYPE_JSON 	= "json";
    const TYPE_XML 	    = "xml";
    const TYPE_JPG 	    = "jpg";

    const HEADER_PLAIN 	= "Content-Type: text/plain; charset=utf-8";
    const HEADER_HTML 	= "Content-Type: text/html; charset=utf-8";
    const HEADER_JSON 	= "Content-Type: application/json; charset=utf-8";
    const HEADER_XML 	= "Content-Type:text/xml; charset=utf8";
    const HEADER_JPG 	= "Content-Type: image/jpeg";

    protected static $_type = self::TYPE_HTML;
    protected static $_header = self::HEADER_HTML;

    static function setType(string $type)
    {
        $response_types_map = [
            self::TYPE_PLAIN  => self::HEADER_PLAIN,
            self::TYPE_HTML   => self::HEADER_HTML,
            self::TYPE_JSON   => self::HEADER_JSON,
            self::TYPE_XML    => self::HEADER_XML,
            self::TYPE_JPG    => self::HEADER_JPG,
        ];
        if (!isset($response_types_map[$type])) {
            throw new Err("Undefined response type [$type]");
        }
        static::$_header = $response_types_map[$type];
        static::$_type = $type;
    }

    static function getType(): string
    {
        return static::$_type;
    }

    static function writeContentTypeHeader()
    {
        header(static::$_header);
    }

    static function redirectAndExit(string $url, bool $permanent = false)
    {
        if ($permanent) {
            header("HTTP/1.1 301 Moved Permanently");
        }
        header("Location: $url");
        exit(0);
    }

    static function redirect404AndExit(string $errMsg = '')
    {
        if (!ErrHandler::isDisplay()) {
            static::redirectAndExit('/404');
        }
        if ($errMsg) {
            ErrHandler::display($errMsg);
            exit(0);
        }
    }

    static function redirect500AndExit(string $errMsg = '')
    {
        if (!ErrHandler::isDisplay()) {
            static::redirectAndExit('/500');
        }
        if ($errMsg) {
            ErrHandler::display($errMsg);
            exit(0);
        }
    }

    static function json($data): string
    {
        static::setType(self::TYPE_JSON);
        return Json::encode($data);
    }

    static function jsonError(string $error, array $extra = []): string
    {
        static::setType(self::TYPE_JSON);
        return Json::encode(array_merge(['error' => $error], $extra));
    }

    static function xml(string $xmlData): string
    {
        static::setType(self::TYPE_XML);
        return $xmlData;
    }

    static function image($imgData, string $imgType)
    {
        if (!in_array($imgType, array(self::TYPE_JPG))) {
            throw new Err("Unsupported image type [$imgType]");
        }
        static::setType($imgType);
        return $imgData;
    }
}