<?php

$installer = $this;

$installer->startSetup();

// 1.5
if ( version_compare(Mage::getVersion(), '1.6', '<') )
{
    $installer->run("DROP TABLE IF EXISTS {$installer->getTable('doofinder_feed/log')};");
}
// 1.6+
else
{
    $installer->getConnection()->dropTable( $installer->getTable('doofinder_feed/log') );
}

/**
 * Log table
 */

// 1.6+
if ( ! version_compare(Mage::getVersion(), '1.6', '<') )
{
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
}
// 1.5
else
{
    $installer->run("

    CREATE TABLE {$installer->getTable('doofinder_feed/log')} (
      `id` int(11) NOT NULL COMMENT 'ID',
      `process_id` varchar(255) NOT NULL COMMENT 'Store Code',
      `type` varchar(255) NOT NULL COMMENT 'Type',
      `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Type',
      `message` text NOT NULL COMMENT 'Message'
    );

    ALTER TABLE {$installer->getTable('doofinder_feed/log')}
    ADD PRIMARY KEY (`id`), ADD KEY `IDX_DOOFINDER_LOG_PROCESS_ID_TYPE` (`process_id`,`type`), ADD KEY `IDX_DOOFINDER_LOG_TIME` (`time`);

    ALTER TABLE {$installer->getTable('doofinder_feed/log')}
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

    ");
}

$installer->endSetup();
