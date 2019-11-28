<?php
/**
 *
 */
class ClassView
{
    static function statusToCssClass(
        string $status, array $statusesSuccess, array $statusesFailed, array $statusesDoing = [], array $statusesInactive = []
    ): string
    {
        $cssClass = '';
        if (in_array($status, $statusesSuccess)) {
            $cssClass = 'success';
        }
        elseif (in_array($status, $statusesFailed)) {
            $cssClass = 'failed';
        }
        elseif (in_array($status, $statusesDoing)) {
            $cssClass = 'doing';
        }
        elseif (in_array($status, $statusesInactive)) {
            $cssClass = 'inactive';
        }
        return $cssClass;
    }

    static function formatAmountPrice(string $value, int $precision = NumFloat::FRACTION_PRECISION_DIGITS): string
    {
        if ($value == 0) {
            return '0.00';
        }
        if (!strstr($value, '.')) {
            return $value . '.00';
        }

        $value = rtrim($value, '0');
        $value = explode('.', $value);

        $res = '';
        $value[0] = number_format($value[0], 0, '.', '&nbsp;');
        $fractionLen = (isset($value[1])) ? strlen($value[1]) : 0;
        if ($fractionLen == 0) {
            $res = $value[0] . '.00';
        }
        else if ($fractionLen == 1) {
            $res = $value[0] . '.' . $value[1] . '0';
        }
        else if ($fractionLen > $precision) {
            $res = $value[0] . '.' . substr($value[1], 0, $precision);
        }
        else {
            $res = $value[0] . '.' . $value[1];
        }
        return $res;
    }
}