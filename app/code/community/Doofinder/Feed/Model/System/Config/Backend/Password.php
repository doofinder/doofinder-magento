<?php

class Doofinder_Feed_Model_System_Config_Backend_Password extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        if (!preg_match('/^[a-zA-Z0-9_-]*$/', $this->getValue()))
        {
            $config = $this->getFieldConfig();

            throw new Exception(Mage::helper('doofinder_feed')->__(
                '%s value is invalid. Only alphanumeric characters with underscores (_) and hyphens (-) are allowed.',
                $config->label
            ));
        }

        return parent::_beforeSave();
    }
}
