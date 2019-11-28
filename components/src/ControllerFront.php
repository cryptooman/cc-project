<?php
/**
 * Usage:
 *      (new ControllerFront( ... ))->run();
 */
class ControllerFront
{
    const INDEX_ROUTE = 'index';

    protected $_controller;
    protected $_action;
    protected $_limits;
    protected $_urlRoutes = [];
    protected $_requestUrl;
    protected $_requestQueryStr;
    protected $_requestUrlArgs;
    protected $_baseUrl;
    protected $_templatePathSkeleton;
    protected $_templatePathSysHeader;
    protected $_templatePathHeader;
    protected $_templatePathFooter;
    protected $_authActionUrl;

    function __construct(
        array $urlRoutes,
        string $requestUrl,
        string $requestQueryStr,
        string $baseUrl,
        string $templatePathSkeleton = '',
        string $templatePathSysHeader = '',
        string $templatePathHeader = '',
        string $templatePathFooter = '',
        string $authActionUrl = '/auth'
    )
    {
        if (!$urlRoutes) {
            throw new ControllerFrontException("Empty url routes");
        }
        $this->_urlRoutes = $urlRoutes;

        if (!$requestUrl) {
            throw new ControllerFrontException("Empty request url");
        }
        $this->_requestUrl = $requestUrl;

        $this->_requestQueryStr = $requestQueryStr;

        if (!$baseUrl) {
            throw new ControllerFrontException("Empty base url");
        }
        $this->_baseUrl = $baseUrl;

        $this->_templatePathSkeleton = $templatePathSkeleton;
        $this->_templatePathSysHeader = $templatePathSysHeader;
        $this->_templatePathHeader = $templatePathHeader;
        $this->_templatePathFooter = $templatePathFooter;

        $this->_authActionUrl = $authActionUrl;
    }

    function run()
    {
        try
        {
            Verbose::echo1("User ID: " . User::id());
            Verbose::echo1("User role: " . User::role());

            $this->_parseRequestUrl();

            Verbose::echo1("Controller: $this->_controller");
            Verbose::echo1("Action: $this->_action");
            Verbose::echo1("Base URL: $this->_baseUrl");

            $this->_checkLimits();

            $controllerObj = new $this->_controller;
            $controllerObj->setRequestUrlArgs($this->_requestUrlArgs);

            View::setGlobal([
                'CONTROLLER'    => $this->_controller,
                'ACTION'        => $this->_action,
                'REQUEST_URL'   => $this->_requestUrl,
                'BASE_URL'      => $this->_baseUrl,
            ]);

            // Run controller action
            $action = $this->_action;
            $controllerResponse = $controllerObj->$action();

            if (in_array(Response::getType(), [Response::TYPE_JSON, Response::TYPE_XML, Response::TYPE_JPG])) {
                $renderControllerResponseOnly = true;
            }
            else {
                $renderControllerResponseOnly = $controllerObj->isRenderControllerResponseOnly();
            }

            if (!$renderControllerResponseOnly) {
                if (!$this->_templatePathSkeleton) {
                    throw new ControllerFrontException("Unable to render skeleton view: Skeleton template is empty");
                }
                $viewSkeleton = new View($this->_templatePathSkeleton);
                $viewSkeleton->set(['body' => $controllerResponse]);

                if($this->_templatePathSysHeader && ($controllerObj->getHtmlSysHeader()) === null)
                {
                    $viewSkeleton->set([
                        'sysHeader' => (new View($this->_templatePathSysHeader))->render()
                    ]);
                }
                if($this->_templatePathHeader && ($controllerObj->getHtmlHeader()) === null)
                {
                    $viewSkeleton->set([
                        'header' => (new View($this->_templatePathHeader))->render()
                    ]);
                }
                if($this->_templatePathFooter && ($controllerObj->getHtmlFooter()) === null)
                {
                    $viewSkeleton->set([
                        'footer' => (new View($this->_templatePathFooter))->render()
                    ]);
                }

                Response::writeContentTypeHeader();
                echo $viewSkeleton->render();
            }
            else {
                // Wrap verbose output into pseudo-comments that can be removed by JS before response parsing
                $verboseOutput = '';
                if (ob_get_length()) {
                    $verboseOutput = '/*' . ob_get_clean() . '*/';
                }
                Response::writeContentTypeHeader();
                echo $verboseOutput . $controllerResponse;
            }
        }
        catch(Exception $e)
        {
            if($e->getCode() == ControllerFrontException::E_UNDEFINED_URL_ROUTE || $e->getCode() == ControllerFrontException::E_BAD_REQUEST) {
                Response::redirect404AndExit($e->getMessage());
            }
            else {
                ErrHandler::handle($e);
            }
        }
    }

    protected function _parseRequestUrl()
    {
        $requestUrl = $this->_requestUrl;
        $queryStr = $this->_requestQueryStr;

        // Remove tail "?" and "&" if no query string
        if (!$queryStr && preg_match("![\?&]$!", $requestUrl)) {
            $requestUrl = preg_replace('![\?&]$!', '', $requestUrl);
        }

        // Add tail "/" if no query string (for easier parsing)
        if (!$queryStr && !preg_match("!/$!", $requestUrl)) {
            $requestUrl .= '/';
        }

        if ($requestUrl == '') {
            $requestUrl = '/';
        }

        $routesBatch = [];
        if ($requestUrl == "/" || preg_match('!^/\?!', $requestUrl)) {
            if (!empty($this->_urlRoutes[static::INDEX_ROUTE])) {
                $routesBatch = $this->_urlRoutes[static::INDEX_ROUTE];
            }
        }
        // Map first part of URL to routes batch (to avoid scan of all routes)
        // I.e. /items/1 will map to 'items' => [ ... ] from $this->_urlRoutes
        elseif (preg_match("!^/?([^/\?]+)!", $requestUrl, $match)) {
            if($match[1] && !empty($this->_urlRoutes[$match[1]])) {
                $routesBatch = $this->_urlRoutes[$match[1]];
            }
        }

        if (!$routesBatch) {
            throw new ControllerFrontException("Unable to get routes for url [$requestUrl]", ControllerFrontException::E_UNDEFINED_URL_ROUTE);
        }

        // Process routes batch (in order they are defined in config)

        $queryStringMacro = 'QS';
        foreach ($routesBatch as $route) {
            $routeAsStr = print_r($route, 1);
            if (empty($route['url'])) {
                throw new ControllerFrontException("Undefined or empty param [url] in route [$routeAsStr]");
            }
            $urlRegx = $route['url'];

            // Remove head slash
            $urlRegx = preg_replace("!^/+(.*)$!", "$1", $urlRegx);
            // Prepare query macros
            $urlRegx = preg_replace("!\[" . $queryStringMacro . "\]!", "(/?\?.*)?", $urlRegx);

            // Prepare url args <> and []
            if (preg_match_all('![<\[](.*?)[>\]]!', $urlRegx, $match)) {
                foreach ($match[1] as $arg) {
                    $arg = explode(':', $arg);
                    if (!preg_match('!^[a-zA-Z\d]+$!', $arg[0])) {
                        throw new ControllerFrontException("Bad url arg [" . $arg[0] . "]: Only a-zA-Z0-9 symbols allowed");
                    }
                }
            }

            // <itemId>
            $urlRegx = preg_replace("!<([a-zA-Z\d]+)>!", "(?<$1>[^/]+?)", $urlRegx);
            // <itemId:\d+>
            $urlRegx = preg_replace_callback("!<([a-zA-Z\d]+):(.+?)>!", function($m) {
                if ($m[2] == 'int') {
                    $m[2] = '\d+';
                }
                return '(?<' . $m[1] . '>' . $m[2] . ')';
            }, $urlRegx);

            // [itemId]
            $urlRegx = preg_replace("!/\[([a-zA-Z\d]+)\]!", "(?<$1>/[^/]+)?", $urlRegx);
            // [itemId:\d+]
            $urlRegx = preg_replace_callback("!/\[([a-zA-Z\d]+):(.+?)\]!", function($m) {
                if ($m[2] == 'int') {
                    $m[2] = '\d+';
                }
                return '(?<' . $m[1] . '>/' . $m[2] . ')?';
            }, $urlRegx);

            // Finalize regx
            $urlRegx = "~^/$urlRegx/?$~";

            if (!preg_match($urlRegx, $requestUrl, $match)) {
                continue;
            }

            // Set controller
            if (empty($route['controller'])) {
                throw new ControllerFrontException("Undefined or empty param [controller] in route [$routeAsStr]");
            }
            $this->_controller = $route['controller'];

            // Set action
            if (empty($route['action'])) {
                throw new ControllerFrontException("Undefined or empty param [action] in route [$routeAsStr]");
            }
            $this->_action = $route['action'];

            // Set limits
            if (empty($route['limits'])) {
                throw new ControllerFrontException("Undefined or empty param [limits] in route [$routeAsStr]");
            }
            foreach ($route['limits'] as $param => $_) {
                if (!in_array($param, ['role', 'post', 'ajax'])) {
                    throw new ControllerFrontException("Bad param [limits.$param] in route [$routeAsStr]");
                }
            }
            if (!isset($route['limits']['role'])) {
                throw new ControllerFrontException("Undefined param [limits.role] in route [$routeAsStr]");
            }
            if (!in_array($route['limits']['role'], ['', User::ROLE_USER, User::ROLE_ADMIN, User::ROLE_SUDO])) {
                throw new ControllerFrontException("Bad param [limits.role] value [" . $route['limits']['role'] . "] in route [$routeAsStr]");
            }
            $this->_limits = $route['limits'];

            // Set url args
            $this->_requestUrlArgs = array();
            if (preg_match_all("!(\[|<)(\w+).*?(\]|>)!", $route['url'], $m)) {
                foreach ($m[2] as $val) {
                    if ($val == $queryStringMacro) {
                        continue;
                    }
                    if (isset($match[$val])) {
                        $this->_requestUrlArgs[$val] = preg_replace("!^/?(.+)!", "$1", $match[$val]); // Cut first "/" if exists
                    }
                    else {
                        $this->_requestUrlArgs[$val] = '';
                    }
                }
            }

            return;
        }

        throw new ControllerFrontException("Undefined route for url [$requestUrl]", ControllerFrontException::E_UNDEFINED_URL_ROUTE);
    }

    protected function _checkLimits()
    {
        if (!empty($this->_limits['post']) && !Request::isPost()) {
            throw new ControllerFrontException('Request must be post', ControllerFrontException::E_BAD_REQUEST);
        }
        if (!empty($this->_limits['ajax']) && !Request::isAjax()) {
            throw new ControllerFrontException('Request must be ajax', ControllerFrontException::E_BAD_REQUEST);
        }

        if (!$this->_limits['role']) {
            return;
        }
        if ($this->_limits['role'] === User::ROLE_USER && User::hasUserAccess()) {
            return;
        }
        if ($this->_limits['role'] === User::ROLE_ADMIN && User::hasAdminAccess()) {
            return;
        }
        if ($this->_limits['role'] === User::ROLE_SUDO && User::hasSudoAccess()) {
            return;
        }

        if (User::authed()) {
            Response::redirect404AndExit('Permission denied');
        }

        Response::redirectAndExit($this->_authActionUrl);
    }
}
