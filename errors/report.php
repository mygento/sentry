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
    $client = Mage::getSingleton('sentry/writer')->getClient();
    $client->captureException($e);
} catch (Exception $ex) {
}

$processor->processReport();
