<?php

class Doofinder_Feed_Model_Observer
{

    protected $xmlPath = 'feed.xml';

    public function generateFeed()
    {

        $stepSize = 5;
        $lastRun = 0;

        $options = array(
            '_limit_' => $stepSize,
            '_offset_' => $lastRun,
            'store_code' => 'default',
            'grouped' => true,
            // Calculate the minimal price with the tier prices
            'minimal_price' => false,
            // Not logged in by default
            'customer_group_id' => 0,
        );

        $generator = Mage::getSingleton('doofinder_feed/generator', $options);
        $xmlData = $generator->run($options);
        file_put_contents(Mage::getBaseDir('media').DS.'doofinder'.DS.$this->xmlPath, $xmlData);
    }
}
