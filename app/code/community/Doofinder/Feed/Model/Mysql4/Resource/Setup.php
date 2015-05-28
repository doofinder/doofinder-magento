<?php
class Doofinder_Feed_Model_Mysql4_Resource_Setup extends Mage_Core_Model_Resource_Setup {

    protected function _construct() {
        $this->_init('doofinder_feed/cron', 'name');
    }
}
