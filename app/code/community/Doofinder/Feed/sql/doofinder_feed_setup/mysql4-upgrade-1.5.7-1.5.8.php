<?php
$installer = $this;
$installer->startSetup();


// Remove unnecessary columns doofinder table
$installer->getConnection()
    ->dropColumn($installer->getTable('cron/schedule'),'store_code');

$installer->getConnection()
    ->dropColumn($installer->getTable('cron/schedule'),'offset');

$installer->getConnection()
    ->dropColumn($installer->getTable('cron/schedule'), 'website_id');



$installer->endSetup();
