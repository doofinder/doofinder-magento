<?php
class Doofinder_Feed_Block_Adminhtml_Widget_Button_Generate extends Mage_Adminhtml_Block_Widget_Button
{
    protected function _prepareLayout()
    {
        $script = "<script type=\"text/javascript\">
            function saveAndGenerate() {
                $(configForm.formId)
                    .insert('<input type=\"hidden\" name=\"generate\" value=\"1\"/>')
                configForm.submit();
            }
        </script>";

        $this->setData(array(
            'type' => 'button',
            'label' => 'Save & Generate',
            'on_click' => 'saveAndGenerate()',
            'after_html' => $script,
            'class' => 'save',
        ));

        parent::_prepareLayout();
    }
}
