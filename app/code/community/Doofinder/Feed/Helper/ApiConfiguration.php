<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Helpers
 * @package    Doofinder_Feed
 * @version    1.9.0

 * Class Doofinder_Feed_Helper_ApiConfiguration
 * The class responsible for providing API configuration values
 */
class Doofinder_Feed_Helper_ApiConfiguration extends Mage_Core_Helper_Abstract
{
    /**
     * @return string
     */
    public function getApiKey()
    {
        return Mage::getStoreConfig('doofinder_search/internal_settings/api_key');
    }

    /**
     * @param string $storeCode
     * @return string
     */
    public function getHashId($storeCode = null)
    {
        $storeCode = $storeCode === null ? Mage::app()->getStore() : $storeCode;
        return Mage::getStoreConfig('doofinder_search/internal_settings/hash_id', $storeCode);
    }

    /**
     * @return string
     */
    public function getManagementServer()
    {
        return Mage::getStoreConfig('doofinder_search/internal_settings/management_server');
    }
}
