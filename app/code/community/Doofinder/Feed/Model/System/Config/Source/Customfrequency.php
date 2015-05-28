<?php
class Doofinder_Feed_Model_System_Config_Source_Customfrequency
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'hourly', 'label'=>Mage::helper('sitemap')->__('Hourly')),
            array('value'=>'daily', 'label'=>Mage::helper('sitemap')->__('Daily')),
            array('value'=>'weekly', 'label'=>Mage::helper('sitemap')->__('Weekly')),
            array('value'=>'monthly', 'label'=>Mage::helper('sitemap')->__('Monthly')),
            array('value'=>'yearly', 'label'=>Mage::helper('sitemap')->__('Yearly')),
        );
    }
}
