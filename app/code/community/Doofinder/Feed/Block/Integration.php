<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Block_Integration extends Mage_Core_Block_Abstract
{
    /**
     * Produce the integration script
     *
     * @return string
     */
    protected function _toHtml()
    {
        $enabled = Mage::getStoreConfig('doofinder_search/layer_settings/enabled', Mage::app()->getStore());
        $script = Mage::getStoreConfig('doofinder_search/layer_settings/script', Mage::app()->getStore());

        if ($enabled) {
            $script .= '<script type="text/javascript">';
            $script .= "if (typeof Varien.searchForm !== 'undefined') Varien.searchForm.prototype.initAutocomplete = function() { $('search_autocomplete').hide(); };";
            $script .= '</script>';

            return $script;
        } else {
            return '';
        }
    }
}
