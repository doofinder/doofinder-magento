<?php

$installer = $this;

$installer->startSetup();

// Add log table
$table = $installer->getConnection()
    ->newTable($installer->getTable('doofinder_feed/log'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'identity'  => true,
        'primary'   => true,
        ), 'ID')
    ->addColumn('process_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable'  => false,
        ), 'Store Code')
    ->addColumn('type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable'  => false,
        ), 'Type')
    ->addColumn('time', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
        ), 'Type')
    ->addColumn('message', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable'  => false,
        ), 'Message');

// Add indexes to log table
$table->addIndex(
    $installer->getIdxName(
        'doofinder_feed/log',
        array(
          'process_id',
          'type',
        ),
        Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
    ),
    array(
        'process_id',
        'type',
    ),
    array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX)
);
$table->addIndex(
    $installer->getIdxName(
        'doofinder_feed/log',
        array(
          'time',
        ),
        Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
    ),
    array(
        'time',
    ),
    array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX)
);

$installer->getConnection()->createTable($table);

if (version_compare(Mage::getVersion(), '1.6', '<'))
{
    $installer->run("

    ALTER TABLE {$installer->getTable('doofinder_feed/log')}
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

    ");
}

$installer->endSetup();
