<?php
/**
 *
 */
class ClassCurrency
{
    static function convertToUsd(float $amount, int $currIdFrom, int $exchangeId = ModelExchanges::BITFINEX_ID, array $dataSnapshot = []): float
    {
        if ($currIdFrom == ModelCurrencies::USD_ID) {
            return $amount;
        }

        // NOTE: Amount can be negative
        if ($amount == 0) {
            return 0;
        }

        $ratio = self::_getCurrRatio($currIdFrom, $exchangeId, $dataSnapshot);

        $converted = NumFloat::floor($amount * $ratio);
        if (!$converted) {
            throw new Err("Bad converted [$converted]: amount [$amount] * ratio [$ratio]: ", func_get_args());
        }

        return $converted;
    }

    static function convertFromUsd(float $amount, int $currIdTo, int $exchangeId = ModelExchanges::BITFINEX_ID, array $dataSnapshot = []): float
    {
        if ($currIdTo == ModelCurrencies::USD_ID) {
            return $amount;
        }

        // NOTE: Amount can be negative
        if ($amount == 0) {
            return 0;
        }

        $ratio = self::_getCurrRatio($currIdTo, $exchangeId, $dataSnapshot);

        $converted = NumFloat::floor($amount / $ratio);
        if (!$converted) {
            throw new Err("Bad converted [$converted]: amount [$amount] / ratio [$ratio]: ", func_get_args());
        }

        return $converted;
    }

    private static function _getCurrRatio(int $currId, int $exchangeId, array $dataSnapshot): float
    {
        $pairId = Cache::make(
            [__CLASS__, __FUNCTION__, $currId],
            function() use($currId, $exchangeId): float {
                $pair = ModelCurrenciesPairs::inst()->getPairByCurrency1IdCurrency2Id($currId, ModelCurrencies::USD_ID);
                if (!$pair) {
                    throw new Err("Failed to get curr pair [$currId, %s]", ModelCurrencies::USD_ID);
                }
                return $pair['id'];
            },
            Cache::EXPIRE_SEC * 5
        );

        if(!$dataSnapshot) {
            $ratio = Cache::make(
                [__CLASS__, __FUNCTION__, $pairId, $exchangeId],
                function() use($pairId, $exchangeId): float {
                    $pairRatio = ModelCurrenciesPairsRatios::inst()->getRatioByPairIdExchangeId($pairId, $exchangeId, ['ratio']);
                    if (empty($pairRatio['ratio'])) {
                        throw new Err("Empty curr pair ratio [$pairId, $exchangeId]");
                    }
                    return $pairRatio['ratio'];
                },
                Cache::EXPIRE_SEC * 5
            );
        }
        else {
            $ratio = 0;
            foreach ($dataSnapshot as $row) {
                if ($pairId == $row['currPairId'] && $exchangeId == $row['exchangeId']) {
                    $ratio = $row['ratio'];
                    break;
                }
            }
            if (!$ratio) {
                throw new Err("Failed to get curr pair ratio [$pairId, $exchangeId] from data snapshot: ", $dataSnapshot);
            }
        }
        return $ratio;
    }
}