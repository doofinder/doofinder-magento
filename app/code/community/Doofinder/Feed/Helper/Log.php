<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Helpers
 * @package    Doofinder_Feed
 * @version    1.8.16
 */

/**
 * Log helper for Doofinder Feed
 *
 * @version    1.8.16
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
    public $debugEnabled;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->debugEnabled = Mage::getStoreConfig('doofinder_cron/feed_settings/debug', false);
    }

    /**
     * Log the feed event.
     *
     * @param Doofinder_Feed_Model_Cron $process
     * @param string $type
     * @param string $message
     * @param boolean $debug Pass message to debug log
     */
    public function log(Doofinder_Feed_Model_Cron $process, $type, $message, $debug = true)
    {
        $debug && $this->debug(sprintf('log(%d, %s) %s', $process->getId(), $type, $message));

        Mage::getModel('doofinder_feed/log')
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
    public function listLogTypes()
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
    public function debug($msg)
    {
        if (!$this->debugEnabled) {
            return;
        }

        Mage::log($msg, null, 'doofinder.log');
    }
}
