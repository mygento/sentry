<?php

/**
 *
 *
 * @category Mygento
 * @package Mygento_Sentry
 * @copyright Copyright © 2016 NKS LLC. (http://www.mygento.ru)
 */
class Mygento_Sentry_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function addLog($text)
    {
        if (Mage::getStoreConfig('sentry/general/debug')) {
            Mage::log($text, null, 'sentry.log', true);
        }
    }

    /**
     * Add useful metadata to the event
     *
     * @param array $event Event data &$event
     * @param null|string                  $notAvailable    Not available
     * @param bool                         $enableBacktrace Flag for Backtrace
     */
    public function addEventMetadata(&$event, $notAvailable = null, $enableBacktrace = false)
    {
        $event
            ->setFile($notAvailable)
            ->setLine($notAvailable)
            ->setBacktrace($notAvailable)
            ->setStoreCode(Mage::app()->getStore()->getCode());
        // Add request time
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $event->setTimeElapsed((float) sprintf('%f', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']));
        } else {
            $event->setTimeElapsed((float) sprintf('%d', time() - $_SERVER['REQUEST_TIME']));
        }
        // Find file and line where message originated from and optionally get backtrace lines
        $basePath = dirname(Mage::getBaseDir()).'/'; // 1 level up in case deployed with symlinks from parent directory
        $nextIsFirst = false;                        // Skip backtrace frames until we reach Mage::log(Exception)
        $recordBacktrace = false;
        $maxBacktraceLines = $enableBacktrace ? (int) Mage::getStoreConfig('sentry/general/max_backtrace_lines') : 0;
        $backtraceFrames = array();
        if (version_compare(PHP_VERSION, '5.3.6') < 0 ) {
            $debugBacktrace = debug_backtrace(false);
        } elseif (version_compare(PHP_VERSION, '5.4.0') < 0) {
            $debugBacktrace = debug_backtrace(
                $maxBacktraceLines > 0 ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS
            );
        } else {
            $debugBacktrace = debug_backtrace(
                $maxBacktraceLines > 0 ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS,
                $maxBacktraceLines + 10
            );
        }
        foreach ($debugBacktrace as $frame) {
            if (($nextIsFirst && $frame['function'] == 'logException')
                || (
                    isset($frame['type'])
                    && $frame['type'] == '::'
                    && $frame['class'] == 'Mage'
                    && substr($frame['function'], 0, 3) == 'log'
                )
            ) {
                if (isset($frame['file']) && isset($frame['line'])) {
                    $event
                        ->setFile(str_replace($basePath, '', $frame['file']))
                        ->setLine($frame['line']);
                    if ($maxBacktraceLines) {
                        $backtraceFrames = array();
                    } elseif ($nextIsFirst) {
                        break;
                    } else {
                        continue;
                    }
                }
                // Don't record backtrace for Mage::logException
                if ($frame['function'] == 'logException') {
                    break;
                }
                $nextIsFirst = true;
                $recordBacktrace = true;
                continue;
            }
            if ($recordBacktrace) {
                if (count($backtraceFrames) >= $maxBacktraceLines) {
                    break;
                }
                $backtraceFrames[] = $frame;
                continue;
            }
        }
        if ($backtraceFrames) {
            $backtrace = array();
            foreach ($backtraceFrames as $index => $frame) {
                // Set file
                if (empty($frame['file'])) {
                    $frame['file'] = 'unknown_file';
                } else {
                    $frame['file'] = str_replace($basePath, '', $frame['file']);
                }
                // Set line
                if (empty($frame['line'])) {
                    $frame['line'] = 0;
                }
                $function = (isset($frame['class']) ? "{$frame['class']}{$frame['type']}":'').$frame['function'];
                $args = array();
                if (isset($frame['args'])) {
                    foreach ($frame['args'] as $value) {
                        $args[] = (is_object($value)
                            ? get_class($value)
                            : ( is_array($value)
                                ? 'array('.count($value).')'
                                : ( is_string($value)
                                    ? "'".(strlen($value) > 28 ? "'".substr($value, 0, 25)."...'" : $value)."'"
                                    : gettype($value)."($value)"
                                )
                            )
                        );
                    }
                }
                $args = implode(', ', $args);
                $backtrace[] = "#{$index} {$frame['file']}:{$frame['line']} $function($args)";
            }
            $event->setBacktrace(implode("\n", $backtrace));
        }
        if (!empty($_SERVER['REQUEST_METHOD'])) {
            $event->setRequestMethod($_SERVER['REQUEST_METHOD']);
        } else {
            $event->setRequestMethod(php_sapi_name());
        }
        if (!empty($_SERVER['REQUEST_URI'])) {
            $event->setRequestMethod($_SERVER['REQUEST_URI']);
        } else {
            $event->setRequestMethod($_SERVER['PHP_SELF']);
        }
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $event->setHttpUserAgent($_SERVER['HTTP_USER_AGENT']);
        }
        // Fetch request data
        $requestData = array();
        if (!empty($_GET)) {
            $requestData[] = '  GET|'.substr(@json_encode($this->filterSensibleData($_GET)), 0, 1000);
        }
        if (!empty($_POST)) {
            $requestData[] = '  POST|'.substr(@json_encode($this->filterSensibleData($_POST)), 0, 1000);
        }
        if (!empty($_FILES)) {
            $requestData[] = '  FILES|'.substr(@json_encode($_FILES), 0, 1000);
        }
        if (Mage::registry('raw_post_data')) {
            $requestData[] = '  RAWPOST|'.substr(Mage::registry('raw_post_data'), 0, 1000);
        }
        $event->setRequestData($requestData ? implode("\n", $requestData) : $notAvailable);
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $event->setRemoteAddress($_SERVER['HTTP_X_FORWARDED_FOR']);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $event->setRemoteAddress($_SERVER['REMOTE_ADDR']);
        } else {
            $event->setRemoteAddress($notAvailable);
        }
        // Add hostname to log message ...
        if (gethostname() !== false) {
            $event->setHostname(gethostname());
        } else {
            $event->setHostname('Could not determine hostname !');
        }
    }

    /**
     * filter sensible data like credit card and password from requests
     *
     * @param  array $data the data to be filtered
     * @return array
     */
     private function filterSensibleData($data){
         return $data;
     }
}
