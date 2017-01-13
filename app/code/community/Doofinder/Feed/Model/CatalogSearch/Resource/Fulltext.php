<?php

class Doofinder_Feed_Model_CatalogSearch_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{
    /**
     * Get stored results select
     *
     * @param int $query_id
     * @param int $attr
     * @return Varien_Db_Select
     */
    protected function getStoredResultsSelect($query_id, $attr = 'product_id')
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from($this->getTable('catalogsearch/result'), $attr)
            ->where('query_id = ?', $query_id)
            ->order('relevance desc');

        return $select;
    }

    /**
     * Get stored results in CatalogSearch cache
     *
     * @param int $query_id
     * @param int $limit
     * @return array
     */
    protected function getStoredResults($query_id, $limit)
    {
        $adapter = $this->_getReadAdapter();
        $select = $this->getStoredResultsSelect($query_id);
        $select->limit($limit);

        $results = array();
        foreach ($adapter->fetchAll($select) as $result) {
            $results[] = $result['product_id'];
        }

        return $results;
    }

    /**
     * Get number of stored results in CatalogSearch cache
     *
     * @param int $query_id
     * @return array
     */
    protected function getStoredResultsCount($query_id)
    {
        $adapter = $this->_getReadAdapter();
        $select = $this->getStoredResultsSelect($query_id, 'COUNT(*)');

        return (int) $adapter->fetchOne($select);
    }

    /**
     * Override prepareResult.
     *
     * @param Mage_CatalogSearch_Model_Fulltext $object
     * @param string $queryText
     * @param Mage_CatalogSearch_Model_Query $query
     *
     * @return Doofinder_Feed_Model_CatalogSearch_Resource_Fulltext
     */
    public function prepareResult($object, $queryText, $query)
    {
        if(!Mage::getStoreConfigFlag('doofinder_search/internal_settings/enable', Mage::app()->getStore())) {
            return parent::prepareResult($object, $queryText, $query);
        }

        $helper = Mage::helper('doofinder_feed/search');

        // Fetch initial results
        $results = $helper->performDoofinderSearch($queryText);

        $adapter = $this->_getWriteAdapter();

        if ($query->getIsProcessed()) {
            $storedResults = $this->getStoredResults($query->getId(), count($results));
            $maxResults = Mage::getStoreConfig('doofinder_search/internal_settings/total_limit', Mage::app()->getStore());

            // Compare results count and checksum
            if (min($helper->getResultsCount(), $maxResults) == $this->getStoredResultsCount($query->getId()) &&
                $this->calculateChecksum($results) == $this->calculateChecksum($storedResults)) {
                
                // Set search results
                $this->setResults($storedResults);
                return $this;
            }

            // Delete results
            $select = $adapter->select()
                ->from($this->getTable('catalogsearch/result'), 'product_id')
                ->where('query_id = ?', $query->getId());
            $adapter->query($adapter->deleteFromSelect($select, $this->getTable('catalogsearch/result')));
        }

        try {

            // Fetch all results
            $results = $helper->getAllResults();

            if (!empty($results)) {
                $data = array();
                $relevance = count($results);

                // Filter out ids to only those that exists in db
                $productCollection = Mage::getModel('catalog/product')->getCollection()
                    ->addAttributeToSelect('entity_id')
                    ->addAttributeToFilter('entity_id', array('in' => $results))
                    ->load();
                foreach ($productCollection as $product) {
                    $productIds[] = $product->getId();
                }
                $results = array_intersect($results, $productIds);

                foreach($results as $product_id) {
                    $data[] = array(
                        'query_id'   => $query->getId(),
                        'product_id' => $product_id,
                        'relevance'  => $relevance--,
                    );
                }

                $adapter->insertOnDuplicate($this->getTable('catalogsearch/result'), $data);

                // Set search results
                $this->setResults($results);
            }

            $query->setIsProcessed(1);

        } catch (Exception $e) {
            Mage::logException($e);
            return parent::prepareResult($object, $queryText, $query);
        }

        return $this;
    }

    /**
     * Set search results
     *
     * @param array[int] $results
     * @notice Required for Magento 1.9.3.0+
     */
    protected function setResults(array $results)
    {
        $data = array();
        $relevance = count($results);
        
        foreach ($results as $productId) {
                $data[$productId] = $relevance--;
        }

        $this->_foundData = $data;
    }

    /**
     * Calculate results checksum
     *
     * @param array[int] $results
     * @return string
     */
    protected function calculateChecksum(array $results)
    {
        return hash('sha256', implode(',', $results));
    }
}
