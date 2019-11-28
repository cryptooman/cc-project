<?php
/**
 *
 */
require_once dirname(__FILE__) . '/../classes/ClassCliScript.php';
ClassCliScript::run(basename(__FILE__), [], [], function($args) {
    Daemon::run(function() {

        (new ClassCronCurrPairsRatiosUpdate)->run();

    }, [], Daemon::LIFETIME_MONTH, ClassCronCurrPairsRatiosUpdate::UPDATE_FREQUENCY_SEC);
});
