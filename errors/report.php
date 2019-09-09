<?php
require_once 'processor.php';

$processor = new Error_Processor();

if (isset($reportData) && is_array($reportData)) {
    $processor->saveReport($reportData);
}

if (!Mage::getStoreConfig('sentry/general/enabled')) {
    $processor->processReport();
    return;
}

try {
    /* @var $client Mygento_Sentry_Model_Writer */
    $client = Mage::getSingleton('sentry/writer');
    $client->captureException($e);
} catch (Exception $ex) {
}

$processor->processReport();
