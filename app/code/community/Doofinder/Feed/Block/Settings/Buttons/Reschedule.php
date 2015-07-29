<?php
class Doofinder_Feed_Block_Settings_Buttons_Reschedule extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $script = "<script type=\"text/javascript\">
            function saveAndReschedule() {
                $(configForm.formId)
                    .insert('<input type=\"hidden\" name=\"reset\" value=\"1\"/>')
                configForm.submit();
            }
        </script>";

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('reschedule')
                    ->setLabel('Save & Reschedule')
                    ->setOnClick("saveAndReschedule()")
                    ->setAfterHtml($script)
                    ->toHtml();
        return $html;
    }

}
