<?php

$installer = $this;

$installer->startSetup();

$query = $installer->getConnection()->select()->from('core_config_data')->where('path = ?', 'doofinder_search/internal_settings/api_key');
$row = $installer->getConnection()->fetchRow($query);
$apiKey = $row['value'];

if ($apiKey) {
    $serverUrl = Mage::getStoreConfig('doofinder_search/internal_settings/management_server');

    if (!$serverUrl) {
        preg_match('/^.*?\-/', $apiKey, $serverPrefix);

        if (isset($serverPrefix[0])) {
            $managementServer = 'https://' . $serverPrefix[0] . 'api.doofinder.com';

            Mage::getModel('core/config')
                ->saveConfig('doofinder_search/internal_settings/management_server', $managementServer);
        }
    }
}

$installer->endSetup();
