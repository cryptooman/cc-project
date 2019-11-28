<?php
/**
 * Usage:
 *      $tmpfile = TmpFile::make();
 */
class TmpFile
{
    static function make(string $basedir = '/dev/shm'): string
    {
        $file = $basedir . '/' . Rand::hash(32) . '.tmp';
        if (file_put_contents($file, '') === false) {
            throw new Err("Failed to create tmp file [$file]");
        }
        return $file;
    }
}