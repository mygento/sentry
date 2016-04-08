<?php

/**
 *
 *
 * @category Mygento
 * @package Mygento_Sentry
 * @copyright Copyright © 2016 NKS LLC. (http://www.mygento.ru)
 */
class Mygento_Sentry_Model_Writer extends Zend_Log_Writer_Abstract
{
    protected $priority = array(
        0 => 'fatal',
        1 => 'fatal',
        2 => 'fatal',
        3 => 'error',
        4 => 'warning',
        5 => 'info',
        6 => 'info',
        7 => 'debug'
    );
    /**
     * sentry client
     *
     * @var Raven_Client
     */
    protected $sentryClient;

    public function __construct()
    {
        if (!Mage::getStoreConfig('sentry/general/enabled')) {
            return;
        }
        $dsn = Mage::getStoreConfig('sentry/general/dsn');
        $this->sentryClient = new Raven_Client($dsn, array(
            'tags' => array(
                'php_version' => phpversion(),
            ),
        ));
    }

    /**
     * Write a message to the log.
     *
     * @param  array $event log data event
     * @return void
     */
    protected function _write($event){
        if (!Mage::getStoreConfig('sentry/general/enabled')) {
            return;
        }
        try {
            Mage::helper('sentry')->addEventMetadata($event);
            $data = array(
                'file' => $event['file'],
                'line' => $event['line'],
            );
            foreach (array('REQUEST_METHOD', 'REQUEST_URI', 'REMOTE_IP', 'HTTP_USER_AGENT') as $key) {
                if (!empty($event[$key])) {
                    $additional[$key] = $event[$key];
                }
            }
            $this->_sentryClient->captureMessage($event['message'], array(), $this->priority[$event['priority']], true, $additional);
        }
        catch (Exception $e) {
            throw new Zend_Log_Exception($e->getMessage(), $e->getCode());
        }
    }
}
