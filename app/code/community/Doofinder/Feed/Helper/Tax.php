<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Helpers
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Helper_Tax extends Mage_Tax_Helper_Data
{
    public function needPriceConversion($store = null)
    {
        $needPriceConversion = parent::needPriceConversion($store);

        // Already needs price conversion so do nothig
        if ($needPriceConversion !== false) {
            return $needPriceConversion;
        }
        $taxMode = Mage::getStoreConfig('doofinder_cron/feed_settings/price_tax_mode', $store);
        // Force price conversion only in case of 'with tax' price export mode
        return $taxMode == Doofinder_Feed_Model_System_Config_Source_Feed_Pricetaxmode::MODE_WITH_TAX;
    }
}
