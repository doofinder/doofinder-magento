<?php

class Doofinder_Feed_Model_System_Config_Backend_Total_Limit extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        if (!$this->getValue()) {
            throw new Exception(Mage::helper('doofinder_feed')->__('Total limit is required.'));
        } else if (!is_numeric($this->getValue())) {
            throw new Exception(Mage::helper('doofinder_feed')->__('Total limit is not a number.'));
        }

        $this->setValue(intval($this->getValue()));

        return parent::_beforeSave();
    }
}
