<?php
class Doofinder_Feed_Model_System_Config_Reset extends Mage_Core_Model_Config_Data
{
    protected function _afterLoad()
    {
        $this->setValue(0);
    }
}
