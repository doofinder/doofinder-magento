<?php

class Doofinder_Feed_Model_Observers_Feed
{

    private $config;

    private $storeCode;


    public function generateFeed($observer)

    {
        $stores = Mage::app()->getStores();
        $helper = Mage::helper('doofinder_feed');

        // Get store code
        $this->storeCode = $observer->getStoreCode();
        Mage::log('Generate feed for '.$this->storeCode);

        // Get store config
        $this->config = $helper->getStoreConfig($this->storeCode);

        if ($this->config['enabled']) {
            try {
                // Get data model for store cron
                $dataModel = Mage::getModel('cron/schedule');
                $data = $dataModel->load($observer->getScheduleId());

                // Get current offset
                $offset = intval($data->getOffset());

                // Get step size
                $stepSize = intval($this->config['stepSize']);

                // Set paths
                $path = Mage::getBaseDir('media').DS.'doofinder'.DS.$this->config['xmlName'];
                $tmpPath = $path.'.tmp';

                // Get job code
                $jobCode = Doofinder_Feed_Model_Observers_Schedule::JOB_CODE;

                // Set options for cron generator
                $options = array(
                    '_limit_' => $stepSize,
                    '_offset_' => $offset,
                    'store_code' => $this->config['storeCode'],
                    'grouped' => $this->_getBoolean($this->config['grouped']),
                    'display_price' => $this->_getBoolean($this->config['display_price']),
                    'minimal_price' => $this->_getBoolean('minimal_price', false),
                    // Not logged in by default
                    'customer_group_id' => 0,
                );

                $generator = Mage::getModel('doofinder_feed/generator', $options);
                $xmlData = $generator->run();


                // If there is new data append to xml.tmp else convert into xml
                if ($xmlData) {
                    $dir = Mage::getBaseDir('media').DS.'doofinder';

                    // If directory doesn't exist create one
                    if (!file_exists($dir)) {
                        $this->_createDirectory($dir);
                    }

                    // If file can not be save throw an error
                    if (!$success = file_put_contents($tmpPath, $xmlData, FILE_APPEND | LOCK_EX)) {
                        Mage::throwException("File can not be saved: {$tmpPath}");
                    }

                    $_productCount = $generator->getProductCount();

                    $exceed = ($offset + $stepSize) >= $_productCount ? true : false;

                    if (!$exceed) {
                        $this->_createNewSchedule($jobCode, $offset);
                    } else {

                        if (!rename($tmpPath, $path)) {
                            throw new Exception("Cannot convert {$tmpPath} to {$path}");
                        }
                    }

                } else {
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

    /**
     * Creates directory.
     * @param string $dir
     * @return bool
     */
    protected function _createDirectory($dir = null) {
        if (!$dir) return false;

        if(!mkdir($dir, 0777, true)) {
           Mage::throwException('Could not create directory: '.$dir);
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
        return $newTime;
    }

    /**
     * Creates new schedule entry.
     * @param string $jobCode
     */

    private function _createNewSchedule($jobCode = Doofinder_Feed_Model_Observers_Schedule::JOB_CODE, $offset = null) {

        $delayInMin = intval($this->config['stepDelay']);
        $timecreated   = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
        $timescheduled = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i") + $delayInMin, date("s"), date("m"), date("d"), date("Y")));

        $newOffset = $offset + $this->config['stepSize'];
        $newSchedule = Mage::getModel('cron/schedule');
        $newSchedule->setCreatedAt($timecreated)
            ->setJobCode($jobCode)
            ->setScheduledAt($timescheduled)
            ->setExecutedAt(null)
            ->setFinishedAt(null)
            ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
            ->setStoreCode($this->storeCode)
            ->setOffset($newOffset)
            ->save();
        Mage::log('New Schedule:');
        Mage::log($newSchedule->getData());

    }


}
