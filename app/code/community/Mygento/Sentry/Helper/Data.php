<?php

/**
 *
 *
 * @category Mygento
 * @package Mygento_Sentry
 * @copyright 2016-2019 NKS LLC. (https://www.mygento.ru)
 */
class Mygento_Sentry_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function addLog($text)
    {
        if (Mage::getStoreConfig('sentry/general/debug')) {
            Mage::log($text, null, 'sentry.log', true);
        }
    }
}
