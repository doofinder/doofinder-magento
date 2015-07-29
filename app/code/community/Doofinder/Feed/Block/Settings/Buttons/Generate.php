<?php
class Doofinder_Feed_Block_Settings_Buttons_Generate extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $storeCode = Mage::app()->getRequest()->getParam('store');
        $display_price = Mage::getStoreConfig('doofinder_cron/feed_settings/display_price', $storeCode);
        $grouped = Mage::getStoreConfig('doofinder_cron/feed_settings/grouped', $storeCode);

        $script = "<script type=\"text/javascript\">
            function changeHandler() {
                $('generate-message').update('Save changes before you generate feed.');
                $(this).setStyle({
                    border: '1px solid rgb(21, 125, 21)'
                })
            }
            $$('.value input, .value select').invoke('observe', 'change', changeHandler);

            function generateFeed(displayPrice, grouped, storeCode){
                var params = 'store_code=' + storeCode + '&display_price=' + displayPrice + '&grouped=' + grouped;
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
                            $('generate-message').update('');
                        }, 3000);
                    }
                });
            }
        </script>
        <span style=\"display: block; height: 18px; margin-top: 2px;\"id=\"generate-message\"></span>";


        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('generate-feed')
                    ->setLabel('Generate Feed')
                    ->setOnClick("generateFeed($display_price, $grouped, '$storeCode')")
                    ->setAfterHtml($script)
                    ->toHtml();
        return $html;
    }

}
