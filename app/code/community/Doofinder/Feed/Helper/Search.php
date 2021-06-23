<?php

class Doofinder_Feed_Helper_Search extends Mage_Core_Helper_Abstract
{
    const DOOFINDER_PAGE_LIMIT = 100;
    const DOOFINDER_RESULTS_LIMIT = 1000;

    protected $_lastSearch = null;
    protected $_lastResults = null;
    protected $_searchError = false;

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
        $apiConfiguration = Mage::helper('doofinder_feed/apiConfiguration');
        $hashId = $apiConfiguration->getHashId();
        $apiKey = $apiConfiguration->getApiKey();
        $limit = Mage::getStoreConfig('doofinder_search/internal_settings/request_limit', Mage::app()->getStore());

        $this->loadDoofinderLibrary();
        $client = new \Doofinder\Api\Search\Client($hashId, $apiKey);

        try {
            $queryArgs = array('rpp' => $limit, 'transformer' => 'onlyid', 'filter' => array());
            // @codingStandardsIgnoreStart
            $results = $client->query($queryText, null, $queryArgs);
            // @codingStandardsIgnoreEnd
        } catch (\Doofinder\Api\Search\Error $e) {
            $results = null;
            $this->_searchError = true;
            Mage::logException($e);
        }

        // Store objects
        $this->_lastSearch = $client;
        $this->_lastResults = $results;

        return $results ? $this->retrieveIds($results) : array();
    }

    /**
     * @return boolean
     */
    public function getSearchError()
    {
        return $this->_searchError;
    }

    /**
     * Retrieve ids from Doofinder Results
     *
     * @param \Doofinder\Api\Search\Results $results
     * @return array
     */
    protected function retrieveIds(\Doofinder\Api\Search\Results $results)
    {
        $ids = array();
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
        if (!$this->_lastResults) {
            return array();
        }

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
        return $this->_lastResults ? $this->_lastResults->getProperty('total') : 0;
    }

    /**
     * Get search results banner data
     *
     * @return array|null
     */
    public function getDoofinderBannerData()
    {
        if ($this->_lastResults) {
            return $this->_lastResults->getProperty('banner');
        }
        return null;
    }

    /**
     * Get Doofinder Api Search Client instance
     *
     * @return \Doofinder\Api\Search\Client
     */
    public function getSearchClient()
    {
        $apiConfiguration = Mage::helper('doofinder_feed/apiConfiguration');
        $hashId = $apiConfiguration->getHashId();
        $apiKey = $apiConfiguration->getApiKey();

        $this->loadDoofinderLibrary();
        $client = new \Doofinder\Api\Search\Client($hashId, $apiKey);
        return $client;
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

        // @codingStandardsIgnoreStart
        if (file_exists($file)) {
            require $file;
        }
        // @codingStandardsIgnoreEnd
    }
}
