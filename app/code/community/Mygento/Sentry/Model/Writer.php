<?php

/**
 * @category Mygento
 * @package Mygento_Sentry
 * @copyright 2016-2018 NKS LLC. (https://www.mygento.ru)
 */
class Mygento_Sentry_Model_Writer extends Zend_Log_Writer_Abstract
{
    /**
     * Translates Zend Framework log levels to Raven log levels.
     * @var array
     */
    private $logLevels = [
      'DEBUG'     => Raven_Client::DEBUG,
      'INFO'      => Raven_Client::INFO,
      'NOTICE'    => Raven_Client::INFO,
      'WARN'      => Raven_Client::WARNING,
      'ERR'       => Raven_Client::ERROR,
      'CRIT'      => Raven_Client::FATAL,
      'ALERT'     => Raven_Client::FATAL,
      'EMERG'     => Raven_Client::FATAL,
    ];

    /**
     * Sentry Client
     * @var Raven_Client
     */
    private $sentryClient;

    /**
     * Trace Enable
     * @var bool
     */
    private $withTrace = true;

    public function __construct()
    {
        $this->sentryClient = $this->getClient();

        if (Mage::getStoreConfig('sentry/general/loglevel')) {
            $this->addFilter(
                (int) Mage::getStoreConfig('sentry/general/loglevel')
            );
        }
    }

    /**
     * Get Client
     * @return Raven_Client
     */
    public function getClient()
    {
        $dsn = Mage::getStoreConfig('sentry/general/dsn');
        $client = new Raven_Client($dsn);

        $client->setAppPath(dirname(BP));
        $client->setEnvironment(
            Mage::getStoreConfig('sentry/general/environment')
        );
        $error_handler = new Raven_ErrorHandler($client);
        $error_handler->registerShutdownFunction();
        return $client;
    }

    /**
     * Write a message to the log.
     *
     * @param array $event log data event
     * @return void
     */
    protected function _write($event)
    {
        $this->sentryClient->captureMessage(
            $event['message'],
            [],
            $this->logLevels[$event['priorityName']],
            $this->withTrace
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
