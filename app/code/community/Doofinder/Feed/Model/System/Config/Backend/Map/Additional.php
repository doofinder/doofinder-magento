<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Model_System_Config_Backend_Map_Additional extends Mage_Adminhtml_Model_System_Config_Backend_Serialized
{
    protected function _beforeSave()
    {
        $_value = $this->getValue();

        unset($_value['additional_mapping'][-1]);

        $this->setValue($_value);

        parent::_beforeSave();
    }
}
