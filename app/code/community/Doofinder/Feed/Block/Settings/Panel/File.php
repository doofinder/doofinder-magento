<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

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
        $store_code = Mage::app()->getRequest()->getParam('store');

        $stores = array();

        if ($store_code) {
            $stores[$store_code] = Mage::getModel('core/store')->load($store_code);
        } else {
            foreach (Mage::app()->getStores() as $store) {
                if ($store->getIsActive()) {
                    $stores[$store->getCode()] = $store;
                }
            }
        }

        $files = array();

        foreach ($stores as $store) {
            $process = Mage::getModel('doofinder_feed/cron')->load($store->getCode(), 'store_code');
            $lastGeneratedName = $process->getLastFeedName();

            $fileUrl = Mage::getBaseUrl('media').'doofinder'.DS.$lastGeneratedName;
            $fileDir = Mage::getBaseDir('media').DS.'doofinder'.DS.$lastGeneratedName;
            if ($lastGeneratedName && file_exists($fileDir)) {
                $files[$store->getCode()] = "<a href='{$fileUrl}' target='_blank'>" . (count($stores) > 1 ? $fileUrl : "Get {$lastGeneratedName}") . "</a>";
            } else {
                $files[$store->getCode()] = "Currently there is no file to preview.";
            }
        }

        $html = '';

        if (count($files) > 1) {
            $html .= '<ul>';
            foreach ($files as $code => $file) {
                $html .= '<li><b>' . $stores[$code]->getName() . ':</b><div>' . $file . '</div></li>';
            }
            $html .= '</ul>';
        } else {
            $html .= reset($files);
        }

        return $html;
    }
}
