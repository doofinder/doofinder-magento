<?php
class Doofinder_Feed_Block_Adminhtml_Widget_Button_Reschedule extends Mage_Adminhtml_Block_Widget_Button
{
    protected function _prepareLayout()
    {
        $script = "<script type=\"text/javascript\">
            function saveAndReschedule() {
                $(configForm.formId)
                    .insert('<input type=\"hidden\" name=\"reset\" value=\"1\"/>')
                configForm.submit();
            }
        </script>";

        $this->setData(array(
            'type' => 'button',
            'label' => 'Save & Reschedule',
            'on_click' => 'saveAndReschedule()',
            'after_html' => $script,
            'class' => 'save',
        ));

        parent::_prepareLayout();
    }
}
