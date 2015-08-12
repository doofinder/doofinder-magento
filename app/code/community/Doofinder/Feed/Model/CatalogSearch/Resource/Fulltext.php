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
        var_dump('Hello');
        $query->setIsProcessed(1);
        return $this;
    }
}
