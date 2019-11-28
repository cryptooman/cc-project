<?php
/**
 *
 */
require_once dirname(__FILE__) . '/../classes/ClassCliScript.php';
ClassCliScript::run(basename(__FILE__), [], [], function($args) {
    Daemon::run(function() {

        (new ClassCronBalancesSyncWithExchanges)->run();

    }, [], Daemon::LIFETIME_MONTH, Daemon::ITERATION_DELAY_SECOND);
});
