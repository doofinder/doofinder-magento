<?php

class Doofinder_Feed_Model_Observers_Feed
{


    public function generateFeed($observer)

    {
        Mage::log($observer->getData());
        $stores = Mage::app()->getStores();
        $helper = Mage::helper('doofinder_feed');
        foreach ($stores as $store) {
            // Get store code
            $storeCode = $store->getCode();

            // Get store config
            $config = $helper->getStoreConfig($storeCode);
            if ($config['enabled']) {
                try {
                    // Get data model for store cron
                    $dataModel = Mage::getModel('doofinder_feed/cron');
                    $data = $dataModel->load($storeCode);

                    // Get current offset
                    $offset = $data->getOffset();

                    // Set paths
                    $path = Mage::getBaseDir('media').DS.'doofinder'.DS.$config['xmlName'];
                    $tmpPath = $path.'.tmp';

                    // Set options for cron generator
                    $options = array(
                        '_limit_' => (int)$config['stepSize'],
                        '_offset_' => (int)$offset,
                        'store_code' => $config['storeCode'],
                        'grouped' => $this->_getBoolean($config['grouped']),
                        // Calculate the minimal price with the tier prices
                        'minimal_price' => $this->_getBoolean($config['price']),
                        // Not logged in by default
                        'customer_group_id' => 0,
                    );
                    Mage::log($options);
                    $generator = Mage::getSingleton('doofinder_feed/generator', $options);
                    $xmlData = $generator->run($options);




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

                        $newOffset = $offset + $config['stepSize'];
                        $data->setOffset($newOffset)->save();

                    } else {
                        $data->setOffset(0)->save();

                        if (!rename($tmpPath, $path)) {
                            throw new Exception("Cannot convert {$tmpPath} to {$path}");
                        }

                    }

                } catch (Exception $e) {
                    Mage::logException('Exception: '.$e);
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




}
