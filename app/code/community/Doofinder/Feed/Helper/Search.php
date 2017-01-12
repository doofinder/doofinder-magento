<?php

class Doofinder_Feed_Helper_Search extends Mage_Core_Helper_Abstract
{
    const DOOFINDER_PAGE_LIMIT = 100;
    const DOOFINDER_RESULTS_LIMIT = 1000;

    protected $_lastSearch = null;
    protected $_lastResults = null;

    /**
     * Perform a doofinder search on given key.
     *
     * @param string $queryText
     * @param int $limit
     * @param int $offset
     *
     * @return array - The array od product ids from first page
     */
    public function performDoofinderSearch($queryText)
    {
        $hashId = Mage::getStoreConfig('doofinder_search/internal_settings/hash_id', Mage::app()->getStore());
        $apiKey = Mage::getStoreConfig('doofinder_search/internal_settings/api_key', Mage::app()->getStore());
        $limit = Mage::getStoreConfig('doofinder_search/internal_settings/request_limit', Mage::app()->getStore());

        $client = new \Doofinder\Api\Search\Client($hashId, $apiKey);
        $results = $client->query($queryText, null, ['rpp' => $limit, 'transformer' => 'onlyid', 'filter' => []]);

        // Store objects
        $this->_lastSearch = $client;
        $this->_lastResults = $results;

        return $this->retrieveIds($results);
    }

    /**
     * Retrieve ids from Doofinder Results
     *
     * @param \Doofinder\Api\Search\Results $results
     * @return array
     */
    protected function retrieveIds(DoofinderResults $results)
    {
        $ids = [];
        foreach ($results->getResults() as $result) {
            $ids[] = $result['id'];
        }

        return $ids;
    }

    /**
     * Fetch all results of last doofinder search
     *
     * @return array - The array of products ids from all pages
     */
    public function getAllResults()
    {
        $limit = Mage::getStoreConfig('doofinder_search/internal_settings/total_limit', Mage::app()->getStore());
        $ids = $this->retrieveIds($this->_lastResults);

        while (count($ids) < $limit && ($results = $this->_lastSearch->nextPage())) {
            $ids = array_merge($ids, $this->retrieveIds($results));
        }

        return $ids;
    }

    /**
     * Returns fetched results count
     *
     * @return int
     */
    public function getResultsCount()
    {
        return $this->_lastResults->getProperty('total');
    }
}
