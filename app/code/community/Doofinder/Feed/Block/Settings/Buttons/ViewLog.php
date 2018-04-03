<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.22
 */

class Doofinder_Feed_Block_Settings_Buttons_ViewLog extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $element->setScopeLabel('');

        $store = Mage::app()->getRequest()->getParam('store');
        $process = Mage::getModel('doofinder_feed/cron')->load($store, 'store_code');

        $url = Mage::helper("adminhtml")->getUrl('adminhtml/doofinderFeedLog/view/processId/' . $process->getId());

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('view-log')
                    ->setLabel('View log')
                    ->setOnClick("setLocation('$url')")
                    ->toHtml();

        return $html;
    }

}
