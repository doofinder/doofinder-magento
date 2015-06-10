<?php
// Add initial data
$stores = Mage::getModel('core/store')->getCollection()
    ->addFieldToFilter('is_active', 1)
    ->addFieldToFilter('code', array('neq' => 'admin'))
    ->load();
$data = array();
foreach ($stores as $store) {
    $code = $store->getCode();
    $model = Mage::getModel('doofinder_feed/cron');
    $data = array(
        'store_code'    =>  $code,
        'status'        =>  'Disabled',
        'message'       =>  'Currently there is no message',
        'complete'      =>  '-',
        'next_run'      =>  '-',
        'next_iteration'=>  '-',
        'last_feed_name'=>  'None',
    );
    $model->setData($data)->save();
}
