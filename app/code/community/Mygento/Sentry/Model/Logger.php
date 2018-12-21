<?php

/**
 * @category Mygento
 * @package Mygento_Sentry
 * @copyright 2016-2018 NKS LLC. (https://www.mygento.ru)
 */
class Mygento_Sentry_Model_Logger extends Zend_Log_Writer_Abstract
{
    /**
     * @var Zend_Log_Writer_Abstract[]
     */
    private $writers = [];

    public function __construct($logFile)
    {
        $this->writers[] = new Zend_Log_Writer_Stream($logFile);
        if (!Mage::getStoreConfig('sentry/general/enabled')) {
            return;
        }
        $this->writers[] = Mage::getSingleton('sentry/writer');
    }

    protected function _write($event)
    {
        foreach ($this->writers as $writer) {
            $writer->write($event);
        }
    }

    public function setFormatter(Zend_Log_Formatter_Interface $formatter)
    {
        foreach ($this->writers as $writer) {
            $writer->setFormatter($formatter);
        }
    }

    /**
     * Satisfy newer Zend Framework
     *
     * @param  array|Zend_Config $config Configuration
     * @return void|Zend_Log_FactoryInterface
     *
     * @SuppressWarnings("unused")
     */
    public static function factory($config)
    {
    }
}
