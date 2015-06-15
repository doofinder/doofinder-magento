<?php
class Doofinder_Feed_Block_Settings_Panel_File extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Error prefix
     * @var string
     */
    const ERROR_PREFIX = "#error#";

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $name = $element->getName();
        $element->setScopeLabel('');
        $store_code = Mage::getSingleton('adminhtml/config_data')->getStore();

        $process = Mage::getModel('doofinder_feed/cron')->load($store_code, 'store_code');
        $lastGeneratedName = $process->getLastFeedName();

        $html = '';
        if ($lastGeneratedName) {
            $path = Mage::getBaseUrl('media').DS.'doofinder'.DS.$lastGeneratedName;
            $html = "<a href='{$path}' target='_blank'>Get {$lastGeneratedName}</a>";
        }

        return $html;
    }
}
