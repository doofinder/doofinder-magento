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
        $fileUrl = Mage::getBaseUrl('media').'doofinder'.DS.$lastGeneratedName;
        $fileDir = Mage::getBaseDir('media').DS.'doofinder'.DS.$lastGeneratedName;
        if ($lastGeneratedName && file_exists($fileDir)) {
            $html = "<a href='{$fileUrl}' target='_blank'>Get {$lastGeneratedName}</a>";
        } else {
            $html = "<p>Currently there is no file to preview.</p>";
        }

        return $html;
    }
}
