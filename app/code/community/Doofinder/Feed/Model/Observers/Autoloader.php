<?php

class Doofinder_Feed_Model_Observers_Autoloader
{
    public function addAutoloader()
    {
    	require_once(Mage::getBaseDir('lib') . DS. 'php-doofinder' . DS .'autoload.php');
        return $this;
    }
}