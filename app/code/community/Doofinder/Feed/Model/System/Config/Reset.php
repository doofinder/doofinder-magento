<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Model_System_Config_Reset extends Mage_Core_Model_Config_Data
{
    protected function _afterLoad()
    {
        $this->setValue(0);
    }
}
