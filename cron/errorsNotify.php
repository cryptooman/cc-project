<?php
/**
 *
 */
require_once dirname(__FILE__) . '/../classes/ClassCliScript.php';
ClassCliScript::run(basename(__FILE__), [], [], function($args) {
    Daemon::run(function() {

        foreach ([
            Config::get('error.logFile') => Config::get('error.notifiedSaveFile')
        ] as $errorsLog => $errorsNotifiedSaveFile)
        {
            Verbose::echo1("Processing errors log [$errorsLog]");

            if (!file_exists($errorsLog)) {
                Verbose::echo1("No errors found in log");
                continue;
            }

            $errorsHash = CmdBash::exec("/usr/bin/md5sum $errorsLog | /usr/bin/cut -d' ' -f1");
            if (!$errorsHash) {
                throw new Err("Failed to get log [$errorsLog] errors hash");
            }

            if (file_exists($errorsNotifiedSaveFile)) {
                if ($errorsHash == file_get_contents($errorsNotifiedSaveFile)) {
                    Verbose::echo1("Already notified about latest errors");
                    continue;
                }
            }

            $errorsReversed = CmdBash::exec("/usr/bin/tac $errorsLog | /usr/bin/head -n 50");
            Verbose::echo1("Latest errors (reversed):\n", $errorsReversed);

            if (!file_put_contents($errorsNotifiedSaveFile, $errorsHash)) {
                throw new Err("Failed write to [$errorsNotifiedSaveFile]");
            }

            Mailer::notifyError("Errors occurred", $errorsReversed);
        }

    }, [], Daemon::LIFETIME_MONTH, Daemon::ITERATION_DELAY_MINUTE);
});
