<?php

$installer = $this;

$installer->startSetup();

// Drop schedule id column in doofinder_feed table
$installer->getConnection()
    ->dropColumn($installer->getTable('doofinder_feed/cron'), 'schedule_id');

// Drop all scheduled doofinder jobs
$collection = Mage::getModel('cron/schedule')->getCollection()
    ->addFieldToFilter('job_code', Doofinder_Feed_Helper_Data::JOB_CODE);

foreach ($collection->getItems() as $item) {
    $item->delete();
}

$installer->endSetup();
