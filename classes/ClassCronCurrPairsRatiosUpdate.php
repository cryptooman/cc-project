<?php
/**
 *
 */
class ClassCronCurrPairsRatiosUpdate extends ClassAbstractCron
{
    const UPDATE_FREQUENCY_SEC          = 60;
    const RATIO_OFFSET_CHECK_SEC        = self::UPDATE_FREQUENCY_SEC + 240; // Check ratio offset within this time frame only
                                                                            // I.e. to avoid error if curr pair was disabled, and then enabled after a while
    const RATIO_OFFSET_MAX              = 0.5;
    const EXCHANGE_TS_OFFSET_SEC_MAX    = 120;

    const E_FAILED = 6000000;

    private $_ratioDeviationCheck = true;

    function __construct($ratioDeviationCheck = true)
    {
        $this->_ratioDeviationCheck = $ratioDeviationCheck;
        parent::__construct();
    }

    function run()
    {
        Verbose::echo1("Update curr pairs ratios");

        $currPairsRatios = ModelCurrenciesPairsRatios::inst()->getRatios(Model::LIMIT_MAX);
        $currPairsRatiosTotal = count($currPairsRatios);
        $currPairsRatiosUpdated = 0;

        if ($currPairsRatios) {
            Verbose::echo1("Curr pairs ratios to update: $currPairsRatiosTotal");
            foreach ($currPairsRatios as $currPairRatio) {
                try {
                    $this->_doRatio($currPairRatio, $currPairsRatiosUpdated);
                }
                catch (Exception $e) {
                    $this->_handleError($e);
                }
                usleep(100000);
            }
        }
        else {
            Verbose::echo1("No curr pairs ratios found");
        }

        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo1("Curr pairs ratios total: $currPairsRatiosTotal");
        Verbose::echo1("Curr pairs ratios updated: $currPairsRatiosUpdated");
        Verbose::echo1("Errors count: $this->_errorsCount");
    }

    private function _doRatio(array $currPairRatio, &$currPairsRatiosUpdated)
    {
        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo2("Doing curr pair [%s] exchange [%s]", $currPairRatio['currPairId'], $currPairRatio['exchangeId']);

        // Disabled: Now inactive curr pair is also used in margin trade, so ratio must be updated anyway
        //if (!ModelCurrenciesPairs::inst()->getActivePairById($currPairRatio['currPairId'], ['id'])) {
        //    Verbose::echo2("Curr pair is inactive");
        //    return;
        //}

        $error = '';
        $response = ClassAbstractExchangeApi::getApi($currPairRatio['exchangeId'])->getCurrPairRatio($currPairRatio['currPairId'], $error);
        if (!$response) {
            throw new Err(
                "Failed to get ratio for curr pair [%s] exchange [%s]: $error", $currPairRatio['currPairId'], $currPairRatio['exchangeId']
            );
        }
        Verbose::echo2("Response is success: ", $response);

        $this->_checkRatio($currPairRatio, $response['ratio'], $response['exchangeTs']);

        ModelCurrenciesPairsRatios::inst()->update(
            $currPairRatio['currPairId'], $currPairRatio['exchangeId'], $response['ratio'], $response['exchangeTs']
        );
        $currPairsRatiosUpdated++;
    }

    private function _checkRatio(array $currPairRatio, float $ratio, int $exchangeTs)
    {
        if ($currPairRatio['ratio']) {
            if ($ratio <= 0) {
                throw new Err("Bad ratio [$ratio]: ", func_get_args());
            }
            if (
                $this->_ratioDeviationCheck
                && strtotime($currPairRatio['syncedAt']) + self::RATIO_OFFSET_CHECK_SEC >= time()
            ) {
                $ratioMin = $currPairRatio['ratio'] * (1 - self::RATIO_OFFSET_MAX);
                $ratioMax = $currPairRatio['ratio'] * (1 + self::RATIO_OFFSET_MAX);
                if ($ratio < $ratioMin || $ratio > $ratioMax) {
                    throw new Err("Ratio [$ratio] not in range [$ratioMin, $ratioMax]: ", func_get_args());
                }
            }
        }

        $tsMin = time() - self::EXCHANGE_TS_OFFSET_SEC_MAX;
        $tsMax = time() + self::EXCHANGE_TS_OFFSET_SEC_MAX;
        if ($exchangeTs < $tsMin || $exchangeTs > $tsMax) {
            throw new Err("Exchange ts [$exchangeTs] not in range [$tsMin, $tsMax]: ", func_get_args());
        }
    }

    private function _handleError(Exception $e)
    {
        $errCodesMap = [
            self::E_FAILED => [
                'fatal' => true,
            ],
        ];

        $errCode = $e->getCode();
        if (isset($errCodesMap[$errCode])) {
            $err = $errCodesMap[$errCode];
        }
        else {
            $err = $errCodesMap[self::E_FAILED];
        }

        $this->_errorsCount++;

        if (!$err['fatal']) {
            Verbose::echo1("ERROR: " . $e->getMessage());
        }
        else {
            throw $e;
        }
    }
}