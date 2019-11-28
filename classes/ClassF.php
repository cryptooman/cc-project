<?php
/**
 * Various project functions
 */
class ClassF
{
    // To complicate various attacks (brute-force, database scan/flood, ...)
    static function sleepLong()
    {
        usleep(random_int(1000000, 1500000));
    }

    // To complicate various attacks
    static function sleepShort()
    {
        usleep(random_int(100000, 150000));
    }

    // $position = ['before', <some-key>]
    // $position = ['after', <some-key>]
    static function insertElementIntoArray(array $arr, $key, $value, array $position): array
    {
        $res = [];
        foreach ($arr as $k => $v) {
            if ($position[0] == 'before' && $k == $position[1]) {
                $res[$key] = $value;
            }
            $res[$k] = $v;
            if ($position[0] == 'after' && $k == $position[1]) {
                $res[$key] = $value;
            }
        }
        return $res;
    }
}