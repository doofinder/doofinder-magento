<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Block_Settings_Buttons_Generate extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $element->setScopeLabel('');

        $storeCode = Mage::app()->getRequest()->getParam('store');
        $url = Mage::helper("adminhtml")->getUrl('adminhtml/doofinderFeedFeed/generate', array('store' => $storeCode));

        $script = "<script type=\"text/javascript\">
            function generateFeed() {
                var call = new Ajax.Request('" . $url . "', {
                    method: 'get',
                    onComplete: function(transport) {
                        alert(transport.responseText);
                        window.location.reload();
                    }
                });
            }
        </script>";

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('generate-feed')
                    ->setLabel('Start Feed Generation Now')
                    ->setOnClick("confirm('No changes will be saved, feed will be rescheduled (if there\'s a process running it will be stopped and the feed will be reset). Do you want to proceed?') && generateFeed()")
                    ->setAfterHtml($script)
                    ->toHtml();
        return $html;
    }

}
