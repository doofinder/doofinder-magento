<?php
class Doofinder_Feed_Block_Settings_Buttons_Generate extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $minimal_price = Mage::getStoreConfig('doofinder_cron/settings/minimal_price');
        $grouped = Mage::getStoreConfig('doofinder_cron/settings/grouped');
        $url = "/doofinder/feed/index?minimal_price={$minimal_price}&grouped={$grouped}";

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('scalable')
                    ->setLabel('Generate Feed')
                    ->setOnClick("setLocation('$url')")
                    ->toHtml();
        return $html;
    }
}
?>
