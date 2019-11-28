<?php
/**
 *
 */
class Form
{
    static function isConsistentUploadFileArray(string $uploadVarName): bool
    {
        if(!isset($_FILES[$uploadVarName])) {
            return null;
        }

        // [name] => pages_2__sample-1.pdf
        // [type] => application/x-unknown
        // [tmp_name] => /tmp/phpP0i91g
        // [error] => 0
        // [size] => 249166
        if(
            !isset($_FILES[$uploadVarName]['name']) || !$_FILES[$uploadVarName]['name'] ||
            !isset($_FILES[$uploadVarName]['type']) || !$_FILES[$uploadVarName]['type'] ||
            !isset($_FILES[$uploadVarName]['tmp_name']) || !$_FILES[$uploadVarName]['tmp_name'] ||
            !isset($_FILES[$uploadVarName]['error']) ||
            !isset($_FILES[$uploadVarName]['size']) || $_FILES[$uploadVarName]['size'] <= 0

        ) {
            return false;
        }
        return true;
    }
}