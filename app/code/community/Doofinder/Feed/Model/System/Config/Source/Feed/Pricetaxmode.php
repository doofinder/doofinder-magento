<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.31
 */

class Doofinder_Feed_Model_System_Config_Source_Feed_Pricetaxmode
{
    const MODE_AUTO = 0;
    const MODE_WITH_TAX = 1;
    const MODE_WITHOUT_TAX = -1;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('doofinder_feed');

        return array(
            array('value' => 0, 'label' => $helper->__('Auto')),
            array('value' => 1, 'label' => $helper->__('With Tax')),
            array('value' => -1, 'label' => $helper->__('Without Tax')),
        );
    }
}
