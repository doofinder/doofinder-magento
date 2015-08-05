<?php

class Doofinder_Feed_Block_Integration extends Mage_Core_Block_Abstract
{
    /**
     * Produce the integration script
     *
     * @return string
     */
    protected function _toHtml()
    {
        $script = Mage::getStoreConfig('doofinder_layer/integration_settings/script', Mage::app()->getStore());

        if ($script) {
            $script .= '<script type="text/javascript">';
            $script .= 'if (typeof Varien.searchForm !== \'undefined\') Varien.searchForm.prototype.initAutocomplete = function() {};';
            $script .= '</script>';

            return $script;
        } else {
            return '';
        }
    }
}
