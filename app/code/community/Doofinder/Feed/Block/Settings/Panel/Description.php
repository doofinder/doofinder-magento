<?php
class Doofinder_Feed_Block_Settings_Panel_Description extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $text = '';

        if (!Mage::app()->getRequest()->getParam('store'))
        {
            $text =  'You can set the rest of the options for each store separately by modifying the Current Configuration Scope.';
        }

        $this->setElement($element);
        $name = $element->getName();
        $element->setScopeLabel('');
        $html = '<p class="doofinder-info" style="width: 400px;">' . $text . '</p>';
        return $html;
    }
}
