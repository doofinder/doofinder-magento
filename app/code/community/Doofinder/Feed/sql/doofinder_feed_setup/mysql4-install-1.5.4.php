<?php

$installer = $this;

$installer->startSetup();

$installer->getConnection()
    ->addColumn($installer->getTable('cron/schedule'),
        'website_id',
        array(
            'type'      => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'nullable'  => false,
            'comment'   => 'Website Id',
        )
    );
$installer->getConnection()
    ->addColumn($installer->getTable('cron/schedule'),
        'store_code',
        array(
            'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
            'nullable'  => false,
            'comment'   => 'Store Code',
            'length'    => 255,
        )
    );

$installer->endSetup();
