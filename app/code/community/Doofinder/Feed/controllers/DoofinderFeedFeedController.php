<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   controllers
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_DoofinderFeedFeedController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Generate feed action
     */
    public function generateAction()
    {
        $storeCode = $this->getRequest()->getParam('store', false);

        $codes = array();

        // Create stores codes array
        if ($storeCode) {
            $codes[] = $storeCode;
        } else {
            $stores = Mage::app()->getStores();
            foreach ($stores as $store) {
                if ($store->getIsActive()) {
                    $codes[] = $store->getCode();
                }
            }
        }

        $scheduleObserver = Mage::getSingleton('doofinder_feed/observers_schedule');

        foreach ($codes as $storeCode) {
            $scheduleObserver->updateProcess($storeCode, true, true, true);
        }

        $this->getResponse()->setBody('Feed generation has been scheduled.');
    }
}
