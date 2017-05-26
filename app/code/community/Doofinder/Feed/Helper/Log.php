<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Helpers
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

/**
 * Log helper for Doofinder Feed
 *
 * @version    1.8.7
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Helper_Log extends Mage_Core_Helper_Abstract
{
    const STATUS = 'status';
    const WARNING = 'warning';
    const ERROR = 'error';

    /**
     * @var boolean
     */
    public $_debugEnabled;

    /**
     * Constructor
     */
    function __construct()
    {
        $this->_debugEnabled = Mage::getStoreConfig('doofinder_cron/feed_settings/debug', false);
    }

    /**
     * Log the feed event.
     *
     * @param Doofinder_Feed_Model_Cron $process
     * @param string $type
     * @param string $message
     * @param boolean $debug Pass message to debug log
     */
    function log(Doofinder_Feed_Model_Cron $process, $type, $message, $debug = true)
    {
        $debug && $this->debug(sprintf('log(%d, %s) %s', $process->getId(), $type, $message));

        $entry = Mage::getModel('doofinder_feed/log')
            ->setProcessId($process->getId())
            ->setType($type)
            ->setMessage($message)
            ->save();

        return $this;
    }

    /**
     * Get available log types
     *
     * @return array
     */
    function listLogTypes()
    {
        return array(
            static::STATUS => $this->__('Status'),
            static::WARNING => $this->__('Warning'),
            static::ERROR => $this->__('Error'),
        );
    }

    /**
     * Log a debug message
     *
     * @param string $msg
     */
    function debug($msg)
    {
        if (!$this->_debugEnabled) {
            return;
        }

        Mage::log($msg, null, 'doofinder.log');
    }
}
