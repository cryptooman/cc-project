<?php
/**
 *
 */
class Cnst
{
    const INT32_MIN             = -2147483648;
    const INT32_MAX             = 2147483647;
    const UINT32_MIN            = 0;
    const UINT32_MAX            = 4294967295;
    const INT64_MIN             = -9223372036854775808;
    const INT64_MAX             = 9223372036854775807;
    const UINT64_MIN            = 0;
    const UINT64_MAX            = 18446744073709551615;

    const FLOAT_MIN             = -9223372036854775808;
    const FLOAT_MAX             = 9223372036854775807;
    const UFLOAT_MIN            = 0;
    const UFLOAT_MIN_NON_ZERO   = NumFloat::FRACTION_PRECISION_FLOAT_MIN;
    const UFLOAT_MAX            = 9223372036854775807;

    const BYTES_1KB             = 1024;
    const BYTES_1MB             = 1048576;
    const BYTES_1GB             = 1073741824;
    const BYTES_1TB             = 1099511627776;

    const SEC_HOUR              = 3600;
    const SEC_DAY               = 86400;
    const SEC_MONTH             = 2592000; // 30 days

    const FILE_NAME_LENGTH_MAX  = 255;
}