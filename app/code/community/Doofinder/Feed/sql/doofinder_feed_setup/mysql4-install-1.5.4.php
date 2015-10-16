<?php

$installer = $this;

$installer->startSetup();

/**
 * Cron table
 */

if (version_compare(Mage::getVersion(), '1.6', '<'))
{
    $installer->run("DROP TABLE IF EXISTS {$installer->getTable('doofinder_feed/cron')};");
}
else
{
    $installer->getConnection()->dropTable( $installer->getTable('doofinder_feed/cron') );
}

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
        ), 'Last Feed Name')
    ->addColumn('offset', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'default'    => 0,
        ), 'Offset')
    ->addColumn('schedule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'default'   => null,
        ), 'Schedule ID');

$installer->getConnection()->createTable($table);

$installer->endSetup();
