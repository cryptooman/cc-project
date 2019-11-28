<?php
/**
 * NOTE: If need: Also add new curr pair to scripts/testDb.php
 */
require_once dirname(__FILE__) . '/../classes/ClassCliScript.php';
ClassCliScript::run(basename(__FILE__), [], [], function($args) {

    $currId = ModelCurrencies::inst()->insert(
        'LTC', 'Litecoin', $isCrypto = true
    );
    verbose::echo1("Created curr id [$currId]");

    $currPairId = ModelCurrenciesPairs::inst()->insert(
        'LTCUSD', 'LTC / USD', $curr1 = $currId, $curr2 = ModelCurrencies::USD_ID, $ordersCount = 2
    );
    verbose::echo1("Created curr pair id [$currPairId]");

    ModelExchangesCurrenciesPairs::inst()->insert(
        ModelExchanges::BITFINEX_ID,
        $currPairId,
        $orderAmountMin = 0.4,
        $orderAmountMax = 5000.0,
        ModelExchangesCurrenciesPairs::ORDER_PRICE_MIN,
        ModelExchangesCurrenciesPairs::ORDER_PRICE_MAX
    );

    (new ClassCronCurrPairsRatiosUpdate(false))->run();

    ModelTotalBalances::inst()->createCurrencyNewBalances($currId);

    ModelSystemApiKeys::inst()->addNewCurrPair($currId, $currPairId);
    ModelUsersApiKeys::inst()->addNewCurrPair($currId, $currPairId);

    verbose::echo1("All done");
});
