<?php
class Doofinder_Feed_Model_Adminhtml_System_Config_Validation_Hashid extends Mage_Core_Model_Config_Data {
    public function save() {
        // Hash id to save
        $hashId = $this->getValue();
        $stores = Mage::app()->getStores();
        foreach ($stores as $store) {
            if ($this->getStoreCode() === $store->getCode())
                continue;
            $code = $store->getCode();
            $scopeHashId = Mage::getStoreConfig('doofinder_search/internal_settings/hash_id', $code);
            if ($hashId !== '' && $hashId === $scopeHashId) {
                Mage::throwException("HashID ".$hashId." is already used in ".$code." store. It must have a unique value.");
                exit;
            }
        }
        return parent::save();
    }
}
