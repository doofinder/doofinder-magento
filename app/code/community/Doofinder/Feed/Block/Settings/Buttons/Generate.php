<?php
class Doofinder_Feed_Block_Settings_Buttons_Generate extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $element->setScopeLabel('');

        $script = "<script type=\"text/javascript\">
            function saveAndGenerate() {
                $(configForm.formId)
                    .insert('<input type=\"hidden\" name=\"generate\" value=\"1\"/>')
                configForm.submit();
            }
        </script>";

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('generate-feed')
                    ->setLabel('Save & Generate Feed')
                    ->setOnClick("saveAndGenerate()")
                    ->setAfterHtml($script)
                    ->toHtml();
        return $html;
    }

}
