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
     * Log the feed event.
     *
     * @param Doofinder_Feed_Model_Cron $process
     * @param string $type
     * @param string $message
     */
    function log(Doofinder_Feed_Model_Cron $process, $type, $message)
    {
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
}
