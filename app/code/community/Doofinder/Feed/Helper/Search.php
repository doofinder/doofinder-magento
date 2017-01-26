<?php

class Doofinder_Feed_Helper_Search extends Mage_Core_Helper_Abstract
{
    const DOOFINDER_PAGE_LIMIT = 100;
    const DOOFINDER_RESULTS_LIMIT = 1000;

    protected $_lastSearch = null;
    protected $_lastResults = null;

    /**
     * @var \Doofinder\Api\Management\SearchEngine[]
     */
    protected $_searchEngines = null;

    /**
     * Load Doofinder PHP library
     */
    protected function loadDoofinderLibrary()
    {
        spl_autoload_register(array($this, 'autoload'), true, true);
    }

    /**
     * Get api key
     *
     * @param string $storeCode
     * @return string
     */
    protected function getApiKey($storeCode = null)
    {
        $storeCode = $storeCode === null ? Mage::app()->getStore() : $storeCode;
        return Mage::getStoreConfig('doofinder_search/internal_settings/api_key', $storeCode);
    }

    /**
     * Get hash id
     *
     * @param string $storeCode
     * @return string
     */
    protected function getHashId($storeCode = null)
    {
        $storeCode = $storeCode === null ? Mage::app()->getStore() : $storeCode;
        return Mage::getStoreConfig('doofinder_search/internal_settings/hash_id', $storeCode);
    }

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
        $hashId = $this->getHashId();
        $apiKey = $this->getApiKey();
        $limit = Mage::getStoreConfig('doofinder_search/internal_settings/request_limit', Mage::app()->getStore());

        $this->loadDoofinderLibrary();
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
    protected function retrieveIds(\Doofinder\Api\Search\Results $results)
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

    /**
     * Get Doofinder Search Engine
     *
     * @param string $storeCode
     * @return \Doofinder\Api\Management\SearchEngine
     */
    public function getDoofinderSearchEngine($storeCode)
    {
        if ($this->_searchEngines === null) {
            $this->_searchEngines = [];

            // Create DoofinderManagementApi instance
            $this->loadDoofinderLibrary();
            $doofinderManagementApi = new \Doofinder\Api\Management\Client($this->getApiKey($storeCode));

            foreach ($doofinderManagementApi->getSearchEngines() as $searchEngine) {
                $this->_searchEngines[$searchEngine->hashid] = $searchEngine;
            }
        }

        // Prepare SearchEngine instance
        $hashId = $this->getHashId($storeCode);
        if (!empty($this->_searchEngines[$hashId])) {
            return $this->_searchEngines[$hashId];
        }

        return false;
    }

    /**
     * Autoloader for 'php-doofinder' library
     */
    protected function autoload($className)
    {
        $libraryPrefix = 'Doofinder\\Api\\';
        $libraryDirectory = Mage::getBaseDir('lib') . DS. 'php-doofinder' . DS . 'src' . DS;

        $len = strlen($libraryPrefix);

        // Binary safe comparison of $len first characters
        if (strncmp($libraryPrefix, $className, $len) !== 0) {
            return;
        }

        $classPath = str_replace('\\', '/', substr($className, $len)) . '.php';
        $file = $libraryDirectory . $classPath;

        if (file_exists($file)) {
            require $file;
        }
    }
}
