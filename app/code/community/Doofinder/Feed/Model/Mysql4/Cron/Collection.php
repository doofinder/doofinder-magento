<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.32
 */
// @codingStandardsIgnoreStart
class Doofinder_Feed_Model_Mysql4_Cron_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
// @codingStandardsIgnoreEnd
    protected function _construct()
    {
            $this->_init('doofinder_feed/cron');
    }
}
