<?php
/**
 * Database migrations
 * Usage:
 *      php dbPatch.php --from=2018-09-30
 */
require_once dirname(__FILE__) . '/../classes/ClassCliScript.php';
ClassCliScript::run(basename(__FILE__), ['from=<yyyy-mm-dd>'], [], function($args) {

    $patches = [
        // Sample:
        //'2018-09-30' => [
        //    'rollIn' => [
        //        [ "CREATE TABLE testPatch (id INT)" ],
        //        [ "ALTER TABLE testPatch ADD name TEXT AFTER id" ],
        //        [ "INSERT INTO testPatch SET %set%", ['%set%' => ['id' => 1, 'name' => 'Name 1']] ],
        //    ],
        //    'rollOut' => [
        //        [ "DELETE FROM testPatch WHERE %set%", ['%set%' => ['id' => 1, 'name' => 'Name 1']] ],
        //        [ "ALTER TABLE testPatch DROP name" ],
        //        [ "DROP TABLE testPatch" ],
        //    ]
        //],

        '2019-01-29' => [
            'rollIn' => [
                [ file_get_contents(DIR_ROOT . '/db/tables/currenciesPairsRatios.sql') ],
                [ "INSERT INTO currenciesPairsRatios SET %set%, created = NOW()", ['%set%' => ['currPairId' => 1, 'exchangeId' => ModelExchanges::BITFINEX_ID]] ],
                [ "INSERT INTO currenciesPairsRatios SET %set%, created = NOW()", ['%set%' => ['currPairId' => 2, 'exchangeId' => ModelExchanges::BITFINEX_ID]] ],
                [ "INSERT INTO currenciesPairsRatios SET %set%, created = NOW()", ['%set%' => ['currPairId' => 3, 'exchangeId' => ModelExchanges::BITFINEX_ID]] ],
                [ "INSERT INTO currenciesPairsRatios SET %set%, created = NOW()", ['%set%' => ['currPairId' => 4, 'exchangeId' => ModelExchanges::BITFINEX_ID]] ],
            ],
            'rollOut' => [
                [], [], [], [],
                [ "DROP TABLE currenciesPairsRatios" ],
            ]
        ],

    ];

    $fromTs = strtotime($args['from']);
    if ($fromTs <= 0) {
        throw new Err("Bad date from provided");
    }

    $model = Model::inst();
    foreach ($patches as $date => $patch) {
        $dateTs = strtotime($date);
        if ($dateTs <= 0) {
            throw new Err("Bad patch date [$date]");
        }

        if ($dateTs >= $fromTs) {
            Verbose::echo1("Doing patch [$date]");
            if (empty($patch['rollIn'])) {
                throw new Err("Empty roll in");
            }
            if (empty($patch['rollOut'])) {
                throw new Err("Empty roll out");
            }
            if (count($patch['rollIn']) != count($patch['rollOut'])) {
                throw new Err("Roll in and roll out steps count mismatch");
            }

            $rollsInSuccess = 0;
            foreach ($patch['rollIn'] as $i => $rollInQuery) {
                $query = $rollInQuery[0];
                array_shift($rollInQuery);
                $args = $rollInQuery;
                try {
                    $qStrf = $model->query($query, ...$args)->strf();
                    $model->query($qStrf)->exec();
                    $rollsInSuccess++;
                    Verbose::echo1("Roll in [$i] success: $qStrf");
                }
                catch (Exception $e) {
                    Verbose::echo1("Error: " . $e->getMessage());
                    Verbose::echo1("Doing roll out");

                    for ($i = 0; $i < $rollsInSuccess; $i++) {
                        if (empty($patch['rollOut'][$i])) {
                            Verbose::echo1("Roll out [$i] skip: empty");
                            continue;
                        }
                        $rollOutQuery = $patch['rollOut'][$i];
                        $query = $rollOutQuery[0];
                        array_shift($rollOutQuery);
                        $args = $rollOutQuery;

                        $qStrf = $model->query($query, ...$args)->strf();
                        $model->query($qStrf)->exec();
                        Verbose::echo1("Roll out [$i] success: $qStrf");
                    }
                    Verbose::echo1("Roll out done");

                    throw new Err("Roll in failed: Errors occurred during patch");
                }
            }
            Verbose::echo1("Roll in done");
        }
    }

});
