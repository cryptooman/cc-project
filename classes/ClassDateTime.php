<?php
/**
 *
 */
class ClassDateTime
{
    const FORMAT_FULL = 'full';         // "2 Oct, 2012 20:31:26"
    const FORMAT_NOSEC = 'nosec';       // "2 Oct, 2012 20:31"
    const FORMAT_NOTIME = 'notime';     // "2 Oct, 2012"
    const FORMAT_NOTODAY = 'notoday';   // If today: "20:31:26"

    static function prettyFormat(string $dateTime, string $format = self::FORMAT_NOTODAY, bool $shortDays = true): string
    {
        if ($shortDays) {
            $_month = [
                '',
                _t('Jan'), _t('Feb'), _t('Mar'), _t('Apr'), _t('May'), _t('Jun'),
                _t('Jul'), _t('Aug'), _t('Sep'), _t('Oct'), _t('Nov'), _t('Dec'),
            ];
            $_days = [
                _t('tda'), _t('yda')
            ];
        }
        else {
            $_month = [
                '',
                _t('January'), _t('February'), _t('March'), _t('April'), _t('May'), _t('June'),
                _t('July'), _t('August'), _t('September'), _t('October'), _t('November'), _t('December'),
            ];
            $_days = [
                _t('today'), _t('yesterday')
            ];
        }

        if(!preg_match('!\d{2}:\d{2}:\d{2}$!', $dateTime)) {
            $dateTime .= ' 00:00:00';
        }
        $date = $dateTime;

        $res_date = "";
        $arr_date = explode(' ', $date); // 2008-06-20 14:31:15

        if (count($arr_date) !== 2) {
            throw new Err("Bad date [$dateTime]");
        }
        list($date, $time) = $arr_date;

        $today = false;
        $yesterday = false;
        if($date == date('Y-m-d')) {
            $res_date = $_days[0];
            $today = true;
        }
        elseif($date == date('Y-m-d', strtotime('-1 day'))) {
            $res_date = $_days[1];
            $yesterday = true;
        }
        // Input date is later then now_date - 1 day
        else {
            $date = explode('-', $date); 		// 2008-06-20
            if (count($date) !== 3) return ""; 	// bad date
            foreach ($date as &$d)				// static cast to int
                $d = (int) $d;
            list($year, $month, $day) = $date;

            if ($year < 1) return "";
            if ($month < 1 || $month > 12 ) return "";
            if ($day < 1 || $day > 31 ) return "";

            $res_date = $day." ".$_month[$month];	// 20 may 2008
            if ($year != date("Y"))					// add year if (not current)
                $res_date = $res_date." ".$year;
        }

        if($format == self::FORMAT_NOTIME)
            return $res_date;

        if($format == self::FORMAT_NOTODAY && $today) {
            $res_date = '';
        }

        $time = explode(":", $time); // 14:31:15
        if (count($time) !== 3) {
            return "";
        }

        if ($format == self::FORMAT_NOSEC) {
            $res_time = $time[0].":".$time[1];
        }
        else {
            $res_time = $time[0].":".$time[1].":".$time[2];
        }

        if (!$res_date && !$res_time) {
            throw new Err("Empty date and time");
        }

        if ($res_date && $res_time) {
            return "$res_date, $res_time";
        }
        elseif ($res_date) {
            return $res_date;
        }
        else {
            return $res_time;
        }
    }

    static function dbDate(int $unixTime): string
    {
        return strftime("%Y-%m-%d", $unixTime);
    }

    static function dbDateTime(int $unixTime): string
    {
        return strftime("%Y-%m-%d %H:%M:%S", $unixTime);
    }

    static function microTime(string $fractionSeparator = '.', int $precision = 6): string
    {
        if ($precision > 6) {
            throw new Err("Max precision is 6");
        }
        $t = explode(' ', trim(microtime()));
        if (!$t || count($t) != 2) {
            throw new Err("Failed to get microtime");
        }
        list($s, $ms) = [$t[1], $t[0]];
        $ms = sprintf('%06f', $ms);
        $ms = substr($ms, 2); // Cut head "0."
        $ms = substr($ms, 0, $precision);
        return (string) ($s . $fractionSeparator . $ms);
    }

    static function nanoTime(string $fractionSeparator = '.'): string
    {
        $t = explode('.', CmdBash::exec('date +%s.%N'));
        if (!$t || count($t) != 2) {
            throw new Err("Failed to get nanotime");
        }
        list($s, $ns) = $t;
        // Add tail zeros
        while (strlen($ns) < 9) {
            $ns .= '0';
        }
        return (string) ($s . $fractionSeparator . $ns);
    }
}