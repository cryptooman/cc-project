<?php
/**
 * Usage
 *      Round::floor(1.000000055) -> 1.00000005
 *      Round::ceil(1.000000055)  -> 1.00000006
 *
 * NOTE: In PHP max value with keeping decimal precision of 8 is 9999999.99999999
 *       Use GMP if need: http://php.net/manual/ru/book.gmp.php
 */
/*
Tests (later move to tests/)
    ini_set('precision', 14);
    foreach ([
        // Floor
        (NumFloat::floor(0) === (float) 0),
        (NumFloat::floor(100) === (float) 100),
        (NumFloat::floor(100.1, 0) === (float) 100),
        (NumFloat::floor(9.9876, 2) === 9.98),
        (NumFloat::isEqual(NumFloat::floor(1.12345678, 0), 1)),
        (NumFloat::isEqual(NumFloat::floor(1.12345678, 1), 1.1)),
        (NumFloat::isEqual(NumFloat::floor(1.12345678, 2), 1.12)),
        (NumFloat::isEqual(NumFloat::floor(1.12345678, 3), 1.123)),
        (NumFloat::isEqual(NumFloat::floor(1.12345678, 4), 1.1234)),
        (NumFloat::isEqual(NumFloat::floor(1.12345678, 5), 1.12345)),
        (NumFloat::isEqual(NumFloat::floor(1.12345678, 6), 1.123456)),
        (NumFloat::isEqual(NumFloat::floor(1.12345678, 7), 1.1234567)),
        (NumFloat::isEqual(NumFloat::floor(1.12345678, 8), 1.12345678)),
        (NumFloat::isEqual(NumFloat::floor(1.123456789, 8), 1.12345678)),
        (NumFloat::isEqual(NumFloat::floor(0.129, 2), 0.12)),
        (NumFloat::isEqual(NumFloat::floor(0), 0)),
        (NumFloat::isEqual(NumFloat::floor(100), 100)),
        (NumFloat::isEqual(NumFloat::floor(-100), -100)),
        (NumFloat::isEqual(NumFloat::floor(0.12345678), 0.12345678)),
        (NumFloat::isEqual(NumFloat::floor(0.00000001), 0.00000001)),
        (!NumFloat::isEqual(NumFloat::floor(0.00000001), 0.00000002)),
        (NumFloat::isEqual(NumFloat::floor(1.00000001), 1.00000001)),
        (NumFloat::isEqual(NumFloat::floor(0.123456789), 0.12345678)),
        (NumFloat::isEqual(NumFloat::floor(1.000000059), 1.00000005)),
        (NumFloat::isEqual(NumFloat::floor(-1.000000051), -1.00000006)),
        (NumFloat::isEqual(NumFloat::floor(-1.000000059), -1.00000006)),
        (NumFloat::isEqual(NumFloat::floor(1E-8), 0.00000001)),
        (NumFloat::isEqual(NumFloat::floor(1E-9), 0)),
        (NumFloat::isEqual(NumFloat::floor(1E-15), 0)),
        (NumFloat::isEqual(NumFloat::floor(1.9E-8), 0.00000001)),
        (NumFloat::isEqual(NumFloat::floor(1.9E-9), 0)),
        (NumFloat::isEqual(NumFloat::floor(1.000000019E-8), 0.00000001)),
        (NumFloat::isEqual(NumFloat::floor(1.000000019E-9), 0)),
        (NumFloat::isEqual(NumFloat::floor(1.0000000000009), 1)),
        (NumFloat::isEqual(NumFloat::floor(1.000000010000009), 1.00000001)),
        (NumFloat::isEqual(NumFloat::floor(1.00000000000009), 1)),
        (NumFloat::isEqual(NumFloat::floor(0.29), 0.29)), // Not 0.289999999
        (NumFloat::isEqual(NumFloat::floor(0.289999999), 0.28999999)),
        (NumFloat::isEqual(NumFloat::floor(1519 / 2623), 0.57910789)),
        (NumFloat::isEqual(NumFloat::floor(99.54 - 99.53), 0.01)),
        (NumFloat::isEqual(NumFloat::floor(99.53 - 99.54), -0.01000001)), // 99.53 - 99.54 = -0.010000000000005 -> -0.01000001
        (NumFloat::isEqual(NumFloat::floor((99.53 - 99.54) * -1), 0.01)),
        (NumFloat::isEqual(NumFloat::floor(15030.00000000000181898), 15030.00000000)),
        (NumFloat::isEqual(NumFloat::floor(99.5400000000000062527760746889), 99.54)),
        (NumFloat::isEqual(NumFloat::floor(99.5400000000000062527760746889), "99.54")),
        (NumFloat::isEqual(NumFloat::ceil(999999.999999998), 1000000)), // Precision truncated
        (NumFloat::isEqual(NumFloat::floor(300.03), 300.03)), // Not 300.02999999
        (NumFloat::isEqual(NumFloat::floor(162503.92800001 - 0.00000001), 162503.928)), // Not 162503.92799999
        (NumFloat::isEqual(NumFloat::floor("0.000000019"), 0.00000001)),
        (NumFloat::isEqual(NumFloat::floor("1.00000000"), 1.00000000)),
        // Ceil
        (NumFloat::ceil(0) === (float) 0),
        (NumFloat::ceil(100) === (float) 100),
        (NumFloat::ceil(100.1, 0) === (float) 100),
        (NumFloat::ceil(9.9876, 2) === 9.99),
        (NumFloat::isEqual(NumFloat::ceil(1.12345678, 0), 1)),
        (NumFloat::isEqual(NumFloat::ceil(1.12345678, 1), 1.2)),
        (NumFloat::isEqual(NumFloat::ceil(1.12345678, 2), 1.13)),
        (NumFloat::isEqual(NumFloat::ceil(1.12345678, 3), 1.124)),
        (NumFloat::isEqual(NumFloat::ceil(1.12345678, 4), 1.1235)),
        (NumFloat::isEqual(NumFloat::ceil(1.12345678, 5), 1.12346)),
        (NumFloat::isEqual(NumFloat::ceil(1.12345678, 6), 1.123457)),
        (NumFloat::isEqual(NumFloat::ceil(1.12345678, 7), 1.1234568)),
        (NumFloat::isEqual(NumFloat::ceil(1.12345678, 8), 1.12345678)),
        (NumFloat::isEqual(NumFloat::ceil(1.123456789, 8), 1.12345679)),
        (NumFloat::isEqual(NumFloat::ceil(0.00000001), 0.00000001)),
        (!NumFloat::isEqual(NumFloat::ceil(0.00000001), 0.00000002)),
        (NumFloat::isEqual(NumFloat::ceil(1.00000001), 1.00000001)),
        (NumFloat::isEqual(NumFloat::ceil(0.123456781), 0.12345679)),
        (NumFloat::isEqual(NumFloat::ceil(1.000000051), 1.00000006)),
        (NumFloat::isEqual(NumFloat::ceil(-1.000000051), -1.00000005)),
        (NumFloat::isEqual(NumFloat::ceil(-1.000000059), -1.00000005)),
        (NumFloat::isEqual(NumFloat::ceil(1E-8), 0.00000001)),
        (NumFloat::isEqual(NumFloat::ceil(1E-9), 0.00000001)),
        (NumFloat::isEqual(NumFloat::ceil(1E-15), 0.00000001)),
        (NumFloat::isEqual(NumFloat::ceil(1.9E-8), 0.00000002)),
        (NumFloat::isEqual(NumFloat::ceil(1.9E-9), 0.00000001)),
        (NumFloat::isEqual(NumFloat::ceil(1.00000019E-8), 0.00000002)),
        (NumFloat::isEqual(NumFloat::ceil(1.00000019E-9), 0.00000001)),
        (NumFloat::isEqual(NumFloat::ceil(1.000000019E-15), 0.00000001)),
        (NumFloat::isEqual(NumFloat::ceil(1.0000000000001), 1.00000001)),
        (NumFloat::isEqual(NumFloat::ceil(1.0000000100001), 1.00000002)),
        (NumFloat::isEqual(NumFloat::ceil(1.00000001000001), 1.00000001)),
        (NumFloat::isEqual(NumFloat::ceil(1.1000000000001), 1.10000001)),
        (NumFloat::isEqual(NumFloat::ceil(1.00000000000001), 1)),
        (NumFloat::isEqual(NumFloat::ceil(999999.99999999), 999999.99999999)),
        (NumFloat::isEqual(NumFloat::ceil(999999.999999998), 1000000)), // Precision truncated
        (NumFloat::isEqual(NumFloat::ceil(0.289999999), 0.29)),
        (NumFloat::isEqual(NumFloat::ceil("0.000000011"), 0.00000002)),
        (NumFloat::isEqual(NumFloat::ceil("1.00000000"), 1.00000000)),
        (NumFloat::isEqual(NumFloat::ceil(0.129, 2), 0.13)),
        (NumFloat::isEqual(NumFloat::ceil(0), 0)),
        (NumFloat::isEqual(NumFloat::ceil(100), 100)),
        (NumFloat::isEqual(NumFloat::ceil(-100), -100)),
        // Round
        (NumFloat::round(0) === (float) 0),
        (NumFloat::round(100) === (float) 100),
        (NumFloat::round(100.1, 0) === (float) 100),
        (NumFloat::round(9.9876, 2) === 9.99),
        (NumFloat::isEqual(NumFloat::round(0.123456784), 0.12345678)),
        (NumFloat::isEqual(NumFloat::round(0.123456785), 0.12345679)),
        (NumFloat::isEqual(NumFloat::round(0.00000001), 0.00000001)),
        (!NumFloat::isEqual(NumFloat::round(0.00000001), 0.00000002)),
        (NumFloat::isEqual(NumFloat::round(1.00000001), 1.00000001)),
        (NumFloat::isEqual(NumFloat::round(1.000000054), 1.00000005)),
        (NumFloat::isEqual(NumFloat::round(1.000000055), 1.00000006)),
        (NumFloat::isEqual(NumFloat::round(-1.000000054), -1.00000005)),
        (NumFloat::isEqual(NumFloat::round(-1.000000055), -1.00000006)),
        (NumFloat::isEqual(NumFloat::round(0.124, 2), 0.12)),
        (NumFloat::isEqual(NumFloat::round(0.125, 2), 0.13)),
        (NumFloat::isEqual(NumFloat::round(0), 0)),
        (NumFloat::isEqual(NumFloat::round(100), 100)),
        (NumFloat::isEqual(NumFloat::round(-100), -100)),
        // Is equal
        (NumFloat::isEqual(0.12345678, 0.12345678)),
        (!NumFloat::isEqual(0.12345678, 0.12345679)),
        (NumFloat::isEqual(0.12345678, 0.12345679, 7)),
        (NumFloat::isEqual(0.00000001, 0.00000001)),
        (NumFloat::isEqual(0.00000001, 0.00000002, 7)),
        (NumFloat::isEqual("0.00000001", 0.00000001)),
        (!NumFloat::isEqual("0.00000001", 0.00000002)),
        (NumFloat::isEqual("0.000000001", 0.000000002)),
        (NumFloat::isEqual("0.00000000", 0)),
        (NumFloat::isEqual("0.00003104", 3.104E-5)),
    ] as $i => $assert) {
        echo ($i + 1) . ' ' . ($assert ? 'ok' : 'FAIL') . "\n";
    }
*/
class NumFloat
{
    const FRACTION_PRECISION_DIGITS = 8;
    const FRACTION_PRECISION_FLOAT_MIN = 0.00000001;

    static function floor(float $num, int $precision = self::FRACTION_PRECISION_DIGITS): float
    {
        static::_checkPrecision($precision);

        $numRounded = round($num, $precision);
        // NOTE: All fraction digits are considered to be significant
        //       Strings are being compared in ASCII alphabetical order
        //       Built-in bccomp() (bc math functions) does not behave as expected on high-precision floats
        //       Use GMP if need
        if ($precision > 0 && (string) $numRounded > (string) $num) {
            // NOTE: All fraction digits are considered to be significant
            $numRounded -= (1 / pow(10, $precision));
        }
        return $numRounded;
    }

    static function ceil(float $num, int $precision = self::FRACTION_PRECISION_DIGITS): float
    {
        static::_checkPrecision($precision);

        $numRounded = round($num, $precision);
        // NOTE: All fraction digits are considered to be significant
        //       Strings are being compared in ASCII alphabetical order
        //       Built-in bccomp() (bc math functions) does not behave as expected on high-precision floats
        //       Use GMP if need
        if ($precision > 0 && (string) $numRounded < (string) $num) {
            $numRounded += (1 / pow(10, $precision));
        }
        return $numRounded;
    }

    static function round(float $num, int $precision = self::FRACTION_PRECISION_DIGITS): float
    {
        static::_checkPrecision($precision);
        return round($num, $precision);
    }

    static function isEqual(float $num1, float $num2, int $precision = self::FRACTION_PRECISION_DIGITS): bool
    {
        static::_checkPrecision($precision);

        if (static::round($num1, $precision) === static::round($num2, $precision)) {
            return true;
        }
        return false;
    }

    private static function _checkPrecision(int $precision)
    {
        if ($precision < 0) {
            throw new Err("Bad precision [$precision]");
        }
        if ($precision > self::FRACTION_PRECISION_DIGITS) {
            throw new Err("Too high precision [$precision]: Use GMP instead");
        }
    }
}