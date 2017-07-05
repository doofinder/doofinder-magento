<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.10
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
        return array(
            array('value' => 0, 'label' => __('Auto')),
            array('value' => 1, 'label' => __('With Tax')),
            array('value' => -1, 'label' => __('Without Tax')),
        );
    }
}
