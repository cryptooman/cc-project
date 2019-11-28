<?php
/**
 * Usage:
 *      views/admin/index.phtml
 *          Name: <?=$this->name?>
 *          Surname: <?=$this->surname?>
 *          Items: <? View::require('admin/index.items') ?>
 *
 *      views/admin/index.items.phtml
 *          <? foreach ($this->items as $item): ?>
 *              ...
 *          <? endforeach ?>
 *
 *      View::init(<templates-base-dir>);
 *      $res = (new View('admin/index'))
 *          ->set([
 *              'name' => 'Mickey',
 *              'surname' => 'Mouse',
 *              'items' => [ ... ]
 *          ])
 *          ->render();
 */
class View
{
    const TEMPLATE_FILE_EXTENSION = 'phtml';

    protected static $_templateBaseDir;
    protected static $_macrosGlobal = [];
    protected static $_inited;

    protected $_templateFile;

    static function init(string $templateBaseDir)
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        if (!is_dir($templateBaseDir)) {
            throw new Err("Not a dir [$templateBaseDir]");
        }
        static::$_templateBaseDir = $templateBaseDir;
    }

    static function setGlobal(array $macros)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        foreach ($macros as $name => $val) {
            static::$_macrosGlobal[strtoupper($name)] = $val;
        }
    }

    static function require(string $templatePath): string
    {
        return require static::_makeTemplateFile($templatePath);
    }

    function __construct(string $templatePath)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $this->_templateFile = static::_makeTemplateFile($templatePath);
    }

    function set(array $macros): View
    {
        foreach($macros as $name => $val) {
            $this->$name = $val;
        }
        return $this;
    }

    function render(): string
    {
        $this->set(static::$_macrosGlobal);

        if (!ob_start(null, $chunkSize = 0)) {
            throw new Err("Failed to start output buffer: Template [$this->_templateFile]");
        }

        require $this->_templateFile;

        if (($bufferContent = ob_get_clean()) === false) {
            throw new Err("Failed to get output buffer content or buffer is inactive: Template [$this->_templateFile]");
        }

        return $bufferContent;
    }

    protected static function _makeTemplateFile(string $templatePath): string
    {
        if ($templatePath[0] == '/') {
            $templateFile = $templatePath . '.' . self::TEMPLATE_FILE_EXTENSION;
        }
        else {
            $templateFile = static::$_templateBaseDir . '/' . $templatePath . '.' . self::TEMPLATE_FILE_EXTENSION;
        }

        if (!is_file($templateFile)) {
            throw new Err("Template [$templateFile] not found");
        }
        return $templateFile;
    }
}