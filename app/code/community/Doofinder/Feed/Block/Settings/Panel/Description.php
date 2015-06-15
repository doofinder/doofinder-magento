<?php
class Doofinder_Feed_Block_Settings_Panel_Description extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $name = $element->getName();
        $element->setScopeLabel('');
        $html = '<p style="color: rgb(21, 125, 21);">You can set the options below for each store separately by modifying the Current Configuration Scope.</p>';
        return $html;
    }


}
