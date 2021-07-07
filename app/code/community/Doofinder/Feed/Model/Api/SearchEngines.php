<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.33

 * Class Doofinder_Feed_Model_Api_SearchEngines
 * The class responsible for managing Search Engines in Doofinder API
 */
class Doofinder_Feed_Model_Api_SearchEngines
{
    /**
     * @var array
     */
    protected $searchEngines;

    /**
     * @var Doofinder_Feed_Helper_Log
     */
    protected $logger;

    /**
     * @var Doofinder_Feed_Model_Api_Client
     */
    protected $api;

    /**
     * @var Doofinder_Feed_Helper_ApiConfiguration
     */
    protected $helper;

    /**
     * Doofinder_Feed_Model_Api_SearchEngines constructor.
     */
    public function __construct()
    {
        $this->logger = Mage::helper('doofinder_feed/log');
        $this->api = Mage::getSingleton('doofinder_feed/api_client');
        $this->helper = Mage::helper('doofinder_feed/apiConfiguration');
    }

    /**
     * @param string $storeCode
     * @return array|null
     */
    public function get($storeCode)
    {
        if (!$this->searchEngines) {
            $url = $this->api->getUrl('search_engines');
            $response = $this->api->sendRequest($url);
            if (isset($response['error'])) {
                $this->logger->debug(
                    sprintf('Cannot list Doofinder search engines: %s', $response['error']['message'])
                );
                return null;
            }

            foreach ($response as $searchEngine) {
                $this->searchEngines[$searchEngine['hashid']] = $searchEngine;
            }
        }

        $hashId = $this->helper->getHashId($storeCode);
        if (!empty($this->searchEngines[$hashId])) {
            return $this->searchEngines[$hashId];
        }
        return null;
    }
}
