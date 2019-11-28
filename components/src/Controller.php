<?php
/**
 * Usage:
 *      ...
 */
abstract class Controller
{
    protected $_requestUrlArgs = [];
    protected $_renderControllerResponseOnlyFlag = false;
    protected $_htmlSysHeader = null;
    protected $_htmlHeader = null;
    protected $_htmlFooter = null;

    function __construct()
    {
    }

    function setRequestUrlArgs(array $requestUrlArgs)
    {
        $this->_requestUrlArgs = $requestUrlArgs;
    }

    function isRenderControllerResponseOnly()
    {
        return $this->_renderControllerResponseOnlyFlag;
    }

    function getHtmlSysHeader()
    {
        return $this->_htmlSysHeader;
    }

    function getHtmlHeader()
    {
        return $this->_htmlHeader;
    }

    function getHtmlFooter()
    {
        return $this->_htmlFooter;
    }

    protected function _getRequestUrlArg(string $name, bool $exception = true)
    {
        if (!isset($this->_requestUrlArgs[$name])) {
            return null;
        }
        return Vars::filter(
            $this->_requestUrlArgs[$name], Vars::REGX, ['!^[a-zA-Z\d_\-]{1,255}$!'], null, $exception ? Vars::NO_DEFAULT_VALUE : null
        );
    }

    protected function _renderControllerResponseOnly()
    {
        $this->_renderControllerResponseOnlyFlag = true;
    }

    protected function _overwriteHtmlSysHeader(string $html)
    {
        $this->_htmlSysHeader = $html;
    }

    protected function _overwriteHtmlHeader(string $html)
    {
        $this->_htmlHeader = $html;
    }

    protected function _overwriteHtmlFooter(string $html)
    {
        $this->_htmlFooter = $html;
    }
}
