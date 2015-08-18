<?php

class Doofinder_Feed_Model_Mysql4_Log extends Mage_Core_Model_Mysql4_Abstract {

    protected function _construct() {
        $this->_init('doofinder_feed/log', 'id');
    }
}
