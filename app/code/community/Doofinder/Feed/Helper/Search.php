<?php

class Doofinder_Feed_Helper_Search extends Mage_Core_Helper_Abstract
{
    /**
     * Perform a doofinder search on given key.
     *
     * @param string $queryText
     *
     * @return array - The array od product ids
     */
    public function performDoofinderSearch($queryText)
    {
        $hashId = Mage::getStoreConfig('doofinder_search/internal_settings/hash_id', Mage::app()->getStore());
        $apiKey = Mage::getStoreConfig('doofinder_search/internal_settings/api_key', Mage::app()->getStore());

        $ids = false;

        $df = new Doofinder_Api($hashId, $apiKey);

        $dfResults = $df->query($queryText, null, array('transformer' => 'onlyid'));

        $ids = array();
        foreach($dfResults->getResults() as $result) {
            $ids[] = $result['id'];
        }

        return $ids;
    }
}
