<?php
/**
 *
 */
class ClassCronAdminsDirectExchangesRequestsProcess extends ClassAbstractCron
{
    const E_FAILED = 5000000;

    function run()
    {
        Verbose::echo1("Processing admin direct requests");

        $dRequests = ModelAdminsDirectExchangesRequests::inst()->getActiveRequests(Model::LIMIT_MAX, ['id', 'requestStrId']);
        $dRequestsTotal = count($dRequests);
        if ($dRequests) {
            Verbose::echo1("Direct requests to process: $dRequestsTotal");

            foreach ($dRequests as $dRequest) {
                try {
                    Verbose::echo2(Verbose::EMPTY_LINE);
                    Verbose::echo1("Doing admin direct request [%s]", $dRequest['id']);
                    (new ClassAdminDirectRequest($dRequest['id']))->run();
                }
                catch (Exception $e) {
                    $this->_handleError($e, $dRequest);
                }
            }
        }
        else {
            Verbose::echo1("No active direct requests found");
        }

        Verbose::echo2(Verbose::EMPTY_LINE);
        Verbose::echo1("Direct requests processed: $dRequestsTotal");
        Verbose::echo1("Errors count: $this->_errorsCount");
    }

    private function _handleError(Exception $e, array $dRequest)
    {
        ModelAdminsDirectExchangesRequests::inst()->setError(
            $dRequest['id'], $dRequest['requestStrId'], ErrHandler::getFormattedErrMsg($e)
        );

        $this->_errorsCount++;

        throw $e;
    }
}