<?php
require_once(Mage::getBaseDir('lib') . DS. 'Doofinder' . DS .'doofinder_api.php');

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
        $ids = false;

        $df = new DoofinderApi($hashId, $apiKey);
        $dfResults = $df->query($queryText, null, array('rpp' => $limit, 'transformer' => 'onlyid', 'filter' => array()));

        // Store objects
        $this->_lastSearch = $df;
        $this->_lastResults = $dfResults;

        return $this->retrieveIds($dfResults);
    }

    /**
     * Retrieve ids from Doofinder Results
     *
     * @param DoofinderResults $dfResults
     * @return array
     */
    protected function retrieveIds(DoofinderResults $dfResults)
    {
        $ids = array();
        foreach($dfResults->getResults() as $result) {
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

        while (count($ids) < $limit && ($dfResults = $this->_lastSearch->nextPage())) {
            $ids = array_merge($ids, $this->retrieveIds($dfResults));
        }

        return $ids;
    }
}
