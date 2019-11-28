<?php
/**
 *
 */
abstract class ClassAbstractBalances
{
    static function sortBalancesForAdmin(array $balances, array $sortMap = [
        ModelAbstractBalances::TYPE_TRADING => 0,
        ModelAbstractBalances::TYPE_POSITION => 1,
        ModelAbstractBalances::TYPE_EXCHANGE => 2,
        ModelAbstractBalances::TYPE_DEPOSIT => 3,
    ]): array
    {
        if (!$balances) {
            return [];
        }
        usort($balances, function($a, $b) use($sortMap) {
            $a['__type'] = $sortMap[$a['type']];
            $b['__type'] = $sortMap[$b['type']];
            if ($a['__type'] == $b['__type']) {
                if (isset($a['currencyId']) && isset($b['currencyId'])) {
                    return ($a['currencyId'] < $b['currencyId']) ? -1 : 1;
                }
                return 0;
            }
            return ($a['__type'] < $b['__type']) ? -1 : 1;
        });
        return $balances;
    }
}