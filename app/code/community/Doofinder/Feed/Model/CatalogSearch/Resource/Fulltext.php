<?php

class Doofinder_Feed_Model_CatalogSearch_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{
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

        $adapter = $this->_getWriteAdapter();

        if (!$query->getIsProcessed()) {
            try {
                $results = Mage::helper('doofinder_feed/search')->performDoofinderSearch($queryText);

                if (!empty($results)) {
                    $data = array();

                    foreach($results as $product_id) {
                        $data[] = array(
                            'query_id'   => $query->getId(),
                            'product_id' => $product_id,
                            //'relevance'  => $product['relevance'],
                        );
                    }

                    $adapter->insertMultiple($this->getTable('catalogsearch/result'), $data);
                }

                $query->setIsProcessed(1);
            } catch (Exception $e) {
                Mage::logException($e);
                return parent::prepareResult($object, $queryText, $query);
            }
        }

        return $this;
    }
}
