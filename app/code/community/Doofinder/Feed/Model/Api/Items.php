<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.9.0

 * Class Doofinder_Feed_Model_Api_Items
 * The class responsible for managing Items in Doofinder API
 */
class Doofinder_Feed_Model_Api_Items
{
    /**
     * @var Doofinder_Feed_Helper_Log
     */
    protected $logger;

    /**
     * @var Doofinder_Feed_Model_Api_SearchEngines
     */
    protected $searchEngines;

    /**
     * @var Doofinder_Feed_Model_Api_Client
     */
    protected $api;

    /**
     * Doofinder_Feed_Model_Api_Items constructor.
     */
    public function __construct()
    {
        $this->logger = Mage::helper('doofinder_feed/log');
        $this->searchEngines = Mage::getSingleton('doofinder_feed/api_searchEngines');
        $this->api = Mage::getSingleton('doofinder_feed/api_client');
    }

    /**
     * @param string $storeCode
     * @param array $products
     * @return boolean
     */
    public function bulkUpdate($storeCode, $products)
    {
        $searchEngine = $this->searchEngines->get($storeCode);
        if (!$searchEngine) {
            $this->logger->debug(
                sprintf('Search engine for store %s does not exists: ', $storeCode)
            );
            return false;
        }

        $url = $this->api->getUrl(
            sprintf('search_engines/%s/indices/product/items/_bulk', $searchEngine['hashid'])
        );
        $response = $this->api->sendRequest(
            $url,
            'POST',
            $products
        );

        if ($response['errors'] === false) {
            return true;
        }
        $this->logger->debug(sprintf(
            'Cannot update products: %s',
            $response['error']['message']
        ));
        return false;
    }
}
