<?php
// Add initial data
$stores = Mage::getModel('core/store')->getCollection()
    ->addFieldToFilter('is_active', 1)
    ->addFieldToFilter('code', array('neq' => 'admin'))
    ->load();
$data = array();

$helper = Mage::helper('doofinder_feed');
foreach ($stores as $store) {
    $code = $store->getCode();
    $model = Mage::getModel('doofinder_feed/cron');
    $data = array(
        'store_code'    =>  $code,
        'status'        =>  $helper::STATUS_DISABLED,
        'message'       =>  $helper::MSG_EMPTY,
        'complete'      =>  '-',
        'next_run'      =>  '-',
        'next_iteration'=>  '-',
        'last_feed_name'=>  'None',
    );
    $model->setData($data)->save();
}
