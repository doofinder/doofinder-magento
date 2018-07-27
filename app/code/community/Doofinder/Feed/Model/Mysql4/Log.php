<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.25
 */
// @codingStandardsIgnoreStart
class Doofinder_Feed_Model_Mysql4_Log extends Mage_Core_Model_Mysql4_Abstract
{
// @codingStandardsIgnoreEnd
    protected function _construct()
    {
        $this->_init('doofinder_feed/log', 'id');
    }
}
