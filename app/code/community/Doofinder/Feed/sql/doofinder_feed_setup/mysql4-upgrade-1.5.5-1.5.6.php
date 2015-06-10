<?php
$installer = $this;
$installer->startSetup();


// Add doofinder table
$table = $installer->getConnection()
    ->newTable($installer->getTable('doofinder_feed/cron'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'identity'  => true,
        'primary'   => true,
        ), 'ID')
    ->addColumn('store_code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable'  => false,
        ), 'Store Code')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'length'    => 255,
        ), 'Status')
    ->addColumn('message', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(

        ), 'Message')
    ->addColumn('error_stack', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'default'    => 0,
        ), 'Error Stack')
    ->addColumn('complete', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'length'    => 12,
        ), 'Complete')
    ->addColumn('next_run', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'length'    => 255,
        ), 'Next Run')
    ->addColumn('next_iteration', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'length'    => 255,
        ), 'Next Iteration')
    ->addColumn('last_feed_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'length'    => 255,
        ), 'Last Feed Name');

$installer->getConnection()->createTable($table);


$installer->endSetup();
