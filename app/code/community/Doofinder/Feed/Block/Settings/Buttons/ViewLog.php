<?php
class Doofinder_Feed_Block_Settings_Buttons_ViewLog extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $process = Mage::getModel('doofinder_feed/cron')->load(Mage::getSingleton('adminhtml/config_data')->getStore(), 'store_code');

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
