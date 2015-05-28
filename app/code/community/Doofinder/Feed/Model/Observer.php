<?php

class Doofinder_Feed_Model_Observer
{


    public function generateFeed()
    {
        $stores = Mage::app()->getStores();
        foreach ($stores as $store) {
            // Get store code
            $storeCode = $store->getCode();

            // Get store config
            $config = $this->getStoreConfig($storeCode);
            if ($config['enabled']) {
                try {
                    $data = Mage::getModel('doofinder_feed/cron');
                    $offset = $data->load('offset');

                    $lastRun = (int)$offset->getValue();


                    $options = array(
                        '_limit_' => $config['stepSize'],
                        '_offset_' => $lastRun,
                        'store_code' => $config['storeCode'],
                        'grouped' => $this->_getBoolean($config['grouped']),
                        // Calculate the minimal price with the tier prices
                        'minimal_price' => $this->_getBoolean($config['price']),
                        // Not logged in by default
                        'customer_group_id' => 0,
                    );

                    $generator = Mage::getSingleton('doofinder_feed/generator', $options);
                    Mage::log($generator->getProductCount());
                    $xmlData = $generator->run();

                    // Set paths
                    $path = Mage::getBaseDir('media').DS.'doofinder'.DS.$config['xmlName'];
                    $tmpPath = $path.'.tmp';

                    if ($xmlData) {
                        $dir = Mage::getBaseDir('media').DS.'doofinder';

                        // If directory doesn't exist create one
                        if (!file_exists($dir)) {
                            $this->_createDirectory($dir);
                        }

                        // If file can not be save throw an error
                        if (!$success = file_put_contents($tmpPath, $xmlData, FILE_APPEND)) {
                            throw new Exception("File can not be saved: {$tmpPath}");
                        }

                        $newRun = $lastRun + (int)$config['stepSize'];
                        $offset->setData('value', $newRun)->save();
                    } else {
                        $offset->setData('value', '0')->save();

                        if (!rename($tmpPath, $path)) {
                            throw new Exception("Cannot convert {$tmpPath} to {$path}");
                        }

                    }
                } catch (Exception $e) {
                    Mage::logError('Exception: '.$e);
                    unset($tmpPath);
                }
            }
        }
    }

    /**
     * Creates directory.
     * @param string $dir
     * @return bool
     */
    protected function _createDirectory($dir = null) {
        if (!$dir) return false;

        if(!mkdir($dir, 0777, true)) {
            throw new Exception('Could not create directory: '.$dir);
        }

        return true;
    }

    /**
     * Cast any value to bool
     * @param mixed $value
     * @param bool $defaultValue
     * @return bool
     */
    protected function _getBoolean($value, $defaultValue = false)
    {
        if (is_numeric($value)) {
            if ($value)
                return true;
            else
                return false;
        }

        $yes = array('true', 'on', 'yes');
        $no  = array('false', 'off', 'no');

        if ( in_array($value, $yes) )
            return true;

        if ( in_array($value, $no) )
            return false;

        return $defaultValue;
    }


    /**
     * Converts time string into array.
     * @param string $time
     * @return array
     */
    protected function timeToArray($time = null) {
        // Declare new time
        $newTime;
        // Validate $time variable
        if(!$time || !is_string($time) || substr_count($time, ',') < 2) {
            Mage::throwException('Incorrect time string.');
            return false;
        }

        list($min, $day, $month,) = explode(',', $time);

        $newTime = array(
            'min'   =>  $min,
            'day'   =>  $day,
            'month'  =>  $month,
        );
        Mage::log($newTime);
        return $newTime;
    }

    /**
     * Process xml filename
     * @param string $name
     * @return bool
     */
    private function processXmlName($name = 'doofinder-{store_code}.xml', $code = 'default') {
        $pattern = '/\{\s*store_code\s*\}/';

        $newName = preg_replace($pattern, $code, $name);
        Mage::log($newName);
        return $newName;
    }

    private  function getStoreConfig($storeCode = 'default') {
        $xmlName = Mage::getStoreConfig('doofinder_cron/settings/name', $storeCode);
        $config = array(
            'enabled'   =>  Mage::getStoreConfig('doofinder_cron/settings/enabled', $storeCode),
            'price'     =>  Mage::getStoreConfig('doofinder_cron/settings/minimal_price', $storeCode),
            'grouped'   =>  Mage::getStoreConfig('doofinder_cron/settings/grouped', $storeCode),
            'stepSize'  =>  Mage::getStoreConfig('doofinder_cron/settings/step', $storeCode),
            'frequency' =>  Mage::getStoreConfig('doofinder_cron/settings/frequency', $storeCode),
            'storeCode' =>  $storeCode,
            'xmlName'   =>  $this->processXmlName($xmlName, $storeCode),
        );
        return $config;
    }

}
