<?php

$installer = $this;

$installer->startSetup();

$installer->getConnection()
    ->addColumn($installer->getTable('cron/schedule'),
        'offset',
        array(
            'type'      =>  Varien_Db_Ddl_Table::TYPE_INTEGER,
            'nullable'  =>  false,
            'comment'   =>  'Offset',
            'after'     =>  'store_code',
            'default'   =>  0,
        )
    );

$installer->endSetup();
