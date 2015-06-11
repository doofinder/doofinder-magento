<?php

class Doofinder_Feed_Model_Cron extends Mage_Core_Model_Abstract {


    protected function _construct() {
        $this->_init('doofinder_feed/cron');

    }


    public function resetData() {
        $helper = Mage::helper('doofinder_feed');
        $this->setStatus($helper::STATUS_DISABLED)
            ->setOffset(0)
            ->setComplete('-')
            ->setNextRun('-')
            ->setNextIteration('-')
            ->save();
    }

}

