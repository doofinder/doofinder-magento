<?php
class Doofinder_Feed_Block_Adminhtml_Widget_Button_Generate extends Mage_Adminhtml_Block_Widget_Button
{
    protected function _prepareLayout()
    {
        $storeCode = Mage::app()->getRequest()->getParam('store');

        $script = "<script type=\"text/javascript\">
            function generateFeed() {
                var call = new Ajax.Request('" . Mage::helper("adminhtml")->getUrl('adminhtml/doofinderFeedFeed/generate/store/' . $storeCode) . "', {
                    method: 'get',
                    onComplete: function(transport) {
                        alert(transport.responseText);
                        window.location.reload();
                    }
                });
            }
        </script>";

        $this->setData(array(
            'type' => 'button',
            'label' => 'Generate Feed',
            'on_click' => "confirm('No changes will be saved, feed will be rescheduled (if there\'s a process running it will be stopped and the feed will be reset). Do you want to proceed?') && generateFeed()",
            'after_html' => $script,
        ));

        parent::_prepareLayout();
    }
}
