<?php
class Doofinder_Feed_Block_Settings_Buttons_Generate extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $minimal_price = Mage::getStoreConfig('doofinder_cron/settings/minimal_price');
        $grouped = Mage::getStoreConfig('doofinder_cron/settings/grouped');
        $storeCode = Mage::app()->getRequest()->getParam('store');

        $script = "<script type=\"text/javascript\">
            function generateFeed(minimalPrice, grouped, storeCode){
                var params = 'store_code=' + storeCode + '&minimal_price=' + minimalPrice + '&grouped=' + grouped;
                console.log(params);
                var reloadurl = '{$this->getUrl('doofinder/feed/generate')}';
                var test = new Ajax.Request(reloadurl, {
                    method: 'get',
                    parameters: params,
                    onLoading: function (transport) {
                        $('generate-message').update('');
                    },
                    onComplete: function(transport) {
                        $('generate-message').update(transport.responseText);
                        setTimeout(function(){
                            $('generate-message').hide();
                        }, 3000);
                    }
                });
            }
        </script>
        <span id=\"generate-message\"></span>";


        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('generate-feed')
                    ->setLabel('Generate Feed')
                    ->setOnClick("generateFeed($minimal_price, $grouped, '$storeCode')")
                    ->setAfterHtml($script)
                    ->toHtml();
        return $html;
    }
}
