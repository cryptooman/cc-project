<?php
/*
Usage:
    HtmlSysHeader::init( ... );
    HtmlSysHeader::setTitle("Page name");
    HtmlSysHeader::setDescription("Page description");
    HtmlSysHeader::setKeywords(['keyword1', 'keyword2', ...]);
    HtmlSysHeader::setCss(['index.css', 'users.css', ...]);
    HtmlSysHeader::setJs(['index.js', 'users.js', ...]);
*/
class HtmlSysHeader {
    protected static $_cssBaseUrl;
    protected static $_cssVersion;
    protected static $_jsBaseUrl;
    protected static $_jsVersion;
    protected static $_title        = [];
    protected static $_description  = [];
    protected static $_keywords     = [];
    protected static $_metaTags     = [];
    protected static $_links        = [];
    protected static $_cssList      = [];
    protected static $_cssListRaw   = [];
    protected static $_jsList       = [];
    protected static $_jsListRaw    = [];
    protected static $_inited;

    static function init(string $cssBaseUrl, string $cssVersion, string $jsBaseUrl, string $jsVersion)
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        static::$_cssBaseUrl = $cssBaseUrl;
        static::$_cssVersion = $cssVersion;
        static::$_jsBaseUrl = $jsBaseUrl;
        static::$_jsVersion = $jsVersion;
    }

    static function setTitle(string $title, bool $replace = false)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($replace) {
            static::$_title = [];
        }
        if ($title = trim($title)) {
            static::$_title[] = $title;
        }
    }

    static function getTitle()
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        // TODO: Enable later
        //if (!static::$_title) {
        //    throw new Err("Meta tag [title] is empty"); // NOTE: Serious SEO impact if no meta tag "title" exists
        //}
        return '<title>' . join(', ', static::$_title) . '</title>' . PHP_EOL;
    }

    static function setDescription(string $description, bool $replace = false)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($replace) {
            static::$_description = [];
        }
        if ($description = trim($description)) {
            static::$_description[] = $description;
        }
    }

    static function getDescription(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_description) {
            return '';
        }
        return '<meta name="description" content="' . join(', ', static::$_description) . '" />' . PHP_EOL;
    }

    static function setKeywords(array $keywords, bool $replace = false)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($replace) {
            static::$_keywords = [];
        }
        if ($keywords) {
            static::$_keywords = array_merge(static::$_keywords, $keywords);
        }
    }

    static function getKeywords(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_keywords) {
            return '';
        }
        return '<meta name="keywords" content="' . join(', ', static::$_keywords) . '" />' . PHP_EOL;
    }

    static function setMetaTags(array $metaTags, bool $replace = false)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($replace) {
            static::$_metaTags = [];
        }
        if ($metaTags) {
            static::$_metaTags = array_merge(static::$_metaTags, $metaTags);
        }
    }

    static function setMetaNoIndex()
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::$_metaTags[] = '<meta name="robots" content="noindex" />';
    }

    static function getMetaTags(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_metaTags) {
            return '';
        }
        return join(PHP_EOL, static::$_metaTags) . PHP_EOL;
    }

    static function setLinks(array $links, bool $replace = false)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if ($replace) {
            static::$_links = [];
        }
        if ($links) {
            static::$_links = array_merge(static::$_links, $links);
        }
    }

    static function getLinks(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!static::$_links) {
            return '';
        }
        return join(PHP_EOL, static::$_links) . PHP_EOL;
    }

    static function setCss(array $cssList)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::$_cssList = array_merge(static::$_cssList, $cssList);
    }

    // If need to place raw css, like: <!--[if IE 6]><link rel="stylesheet" type="text/css" href="css/ie6.css" /><![endif]-->
    static function setCssRaw(array $cssList)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::$_cssListRaw = array_merge(static::$_cssListRaw, $cssList);
    }

    static function getCss(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $res = [];
        foreach(static::$_cssList as $cssUrl) {
            if (preg_match('!^/!', $cssUrl)) {
                $res[] = '<link rel="stylesheet" type="text/css" href="' . $cssUrl . '?' . static::$_cssVersion . '" />';
            }
            else {
                $res[] = '<link rel="stylesheet" type="text/css" href="' . static::$_cssBaseUrl . '/' . $cssUrl . '?' . static::$_cssVersion . '" />';
            }
        }
        foreach(static::$_cssListRaw as $cssRaw) {
            $res[] = $cssRaw;
        }

        if (!$res) {
            return '';
        }
        return join(PHP_EOL, $res) . PHP_EOL;
    }

    static function setJs(array $jsList)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::$_jsList = array_merge(static::$_jsList, $jsList);
    }

    static function setJsRaw(array $jsList)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::$_jsListRaw = array_merge(static::$_jsListRaw, $jsList);
    }

    static function getJs(): string
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $res = [];
        foreach(static::$_jsList as $jsUrl) {
            if (preg_match('!^/!', $jsUrl)) {
                $res[] = '<script type="text/javascript" src="' . $jsUrl . '?' . static::$_jsVersion . '"></script>';
            }
            else {
                $res[] = '<script type="text/javascript" src="' . static::$_jsBaseUrl . '/' . $jsUrl . '?' . static::$_jsVersion . '"></script>';
            }
        }
        foreach(static::$_jsListRaw as $jsRaw) {
            $res[] = $jsRaw;
        }

        if (!$res) {
            return '';
        }
        return join(PHP_EOL, $res) . PHP_EOL;
    }
}
