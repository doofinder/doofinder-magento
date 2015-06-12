<?php
$installer = $this;
$installer->startSetup();


// Add doofinder table
$installer->getConnection()
    ->addColumn($installer->getTable('doofinder_feed/cron'),
    'schedule_id',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'nullable' => true,
        'default' => null,
        'comment' => 'Schedule ID'
    )
);



$installer->endSetup();
