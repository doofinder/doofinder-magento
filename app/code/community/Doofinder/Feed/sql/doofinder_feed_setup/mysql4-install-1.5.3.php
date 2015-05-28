<?php

echo 'Install doofinder feed table and init data.';
$installer = $this;

$installer->startSetup();


// Create table
$tableExists = $installer->getConnection()
    ->isTableExists($installer->getTable('doofinder_feed/cron'));

if (!$tableExists) {
    $table = $installer->getConnection()
        ->newTable($installer->getTable('doofinder_feed/cron'))
        ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => false,
            'primary'   => true,
            ), 'Name')
        ->addColumn('value', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => true,
            ), 'Value');

    $installer->getConnection()->createTable($table);

    // Add init data
    $installer->getConnection()->insert($installer->getTable('doofinder_feed/cron'), array(
        'name'      => 'offset',
        'value'     => '0',
    ));

    $installer->getConnection()->insert($installer->getTable('doofinder_feed/cron'), array(
        'name'      => 'error_stack',
        'value'     => '0',
    ));

    $installer->getConnection()->insert($installer->getTable('doofinder_feed/cron'), array(
        'name'      => 'status',
        'value'     => '',
    ));
    $installer->getConnection()->insert($installer->getTable('doofinder_feed/cron'), array(
        'name'      => 'message',
        'value'     => '',
    ));
}
$installer->endSetup();
