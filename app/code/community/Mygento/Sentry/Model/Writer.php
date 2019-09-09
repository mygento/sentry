<?php

/**
 * @category Mygento
 * @package Mygento_Sentry
 * @copyright 2016-2019 NKS LLC. (https://www.mygento.ru)
 */
class Mygento_Sentry_Model_Writer extends Zend_Log_Writer_Abstract
{
    /**
     * Translates Zend Framework log levels to Raven log levels.
     * @var array
     */
    private $logLevels = [
      'DEBUG'     => \Sentry\Severity::DEBUG,
      'INFO'      => \Sentry\Severity::INFO,
      'NOTICE'    => \Sentry\Severity::INFO,
      'WARN'      => \Sentry\Severity::WARNING,
      'ERR'       => \Sentry\Severity::ERROR,
      'CRIT'      => \Sentry\Severity::FATAL,
      'ALERT'     => \Sentry\Severity::FATAL,
      'EMERG'     => \Sentry\Severity::FATAL,
    ];

    public function __construct()
    {
        \Sentry\init($this->getClientOptions());

        if (Mage::getStoreConfig('sentry/general/loglevel')) {
            $this->addFilter(
                (int) Mage::getStoreConfig('sentry/general/loglevel')
            );
        }
    }

    public function getClientOptions()
    {
        return [
            'dsn' => Mage::getStoreConfig('sentry/general/dsn'),
            'environment' => Mage::getStoreConfig('sentry/general/environment'),
        ];
    }

    public function captureException($e)
    {
        return \Sentry\captureException($e);
    }

    /**
     * Write a message to the log.
     *
     * @param array $event log data event
     * @return void
     */
    protected function _write($event)
    {
        \Sentry\captureMessage(
            $event['message'],
            new \Sentry\Severity($this->logLevels[$event['priorityName']])
        );
    }

    /**
     * Construct a Zend_Log driver
     *
     * @param  array|Zend_Config $config
     * @throws \Exception
     * @return Zend_Log_FactoryInterface
     *
     * @SuppressWarnings("unused")
     */
    public static function factory($config)
    {
    }
}
