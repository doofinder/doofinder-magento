<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Model_Cron extends Mage_Core_Model_Abstract {


    protected function _construct() {
        $this->_init('doofinder_feed/cron');

    }

    public function modeDisabled() {
        $helper = Mage::helper('doofinder_feed');
        $this->setStatus($helper::STATUS_DISABLED)
            ->setOffset(0)
            ->setComplete(null)
            ->setNextRun(null)
            ->setNextIteration(null)
            ->setMessage($helper::MSG_DISABLED)
            ->save();
    }

    public function modeWaiting() {
        $helper = Mage::helper('doofinder_feed');
        $this->setStatus($helper::STATUS_WAITING)
            ->setMessage($helper::MSG_WAITING)
            ->save();
    }


}

