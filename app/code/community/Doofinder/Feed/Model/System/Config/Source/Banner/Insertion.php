<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.26
 */

class Doofinder_Feed_Model_System_Config_Source_Banner_Insertion
{
    public function toOptionArray()
    {
        $helper = Mage::helper('doofinder_feed');
        return array(
            array('value' => 'before', 'label' => $helper->__('Banner before element')),
            array('value' => 'after', 'label' => $helper->__('Banner after element')),
            array('value' => 'prepend', 'label' => $helper->__('Banner at the beginning of element')),
            array('value' => 'append', 'label' => $helper->__('Banner at the end of element')),
            array('value' => 'replace', 'label' => $helper->__('Replace element with banner')),
        );
    }
}
