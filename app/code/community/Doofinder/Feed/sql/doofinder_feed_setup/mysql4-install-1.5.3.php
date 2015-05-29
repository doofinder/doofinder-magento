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
        ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'primary'   => true,
            'nullable'  => false,
        ), 'Store Id')
        ->addColumn('store_code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => false,
            'default'   => 'default',
        ), 'Store Code')
        ->addColumn('error_stack', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
            'default'   => 0,
        ), 'Error Stack')
        ->addColumn('offset', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
            'default'   => 0,
        ), 'Offset')
        ->addColumn('message', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => true,
        ), 'Message')
        ->addColumn('status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => true,
        ), 'Status');

    $installer->getConnection()->createTable($table);

}
$installer->endSetup();
