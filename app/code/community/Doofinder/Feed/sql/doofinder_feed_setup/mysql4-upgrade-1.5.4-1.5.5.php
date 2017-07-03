<?php

$installer = $this;

$installer->startSetup();

// version 1.5
if (version_compare(Mage::getVersion(), '1.6', '<')) {
    $installer->run(
        "ALTER TABLE {$installer->getTable('doofinder_feed/cron')}
        MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;"
    );
}

$installer->endSetup();
