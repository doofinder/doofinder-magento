<?php

class Doofinder_Feed_Model_Observer
{
    /**
     * Xml file name.
     * @var string
     */
    protected $xmlPath;

    /**
     * Crontab enabled settings.
     * @var bool
     */
    protected $enabled;

    /**
     * Crontab grouped settings.
     * @var bool
     */
    protected $grouped;

    /**
     * Crontab minimial price settings.
     * @var bool
     */
    protected $price;

    /**
     * Magento store code.
     * @var string
     */
    protected $storeCode;

    /**
     * Magento cron start time.
     * @var array
     */
    protected $startTime;

    /**
     * Magento cron job frequency.
     * @var array
     */
    protected $frequency;

    /**
     * Step size.
     * @var int
     */
    protected $stepSize;

    public function __construct() {
        $this->enabled = Mage::getStoreConfig('doofinder_cron/settings/enabled', Mage::app()->getStore());
        $this->price = Mage::getStoreConfig('doofinder_cron/settings/minimal_price', Mage::app()->getStore());
        $this->grouped = Mage::getStoreConfig('doofinder_cron/settings/grouped', Mage::app()->getStore());
        $this->storeCode = Mage::app()->getStore()->getCode();
        $this->xmlPath = Mage::getStoreConfig('doofinder_cron/settings/name', Mage::app()->getStore());
        $this->stepSize = Mage::getStoreConfig('doofinder_cron/settings/step', Mage::app()->getStore());


        /*$startTime = Mage::getStoreConfig('doofinder_cron/settings/time', Mage::app()->getStore());
        $frequency = Mage::getStoreConfig('doofinder_cron/settings/frequency', Mage::app()->getStore());
        $this->startTime = $this->timeToArray($startTime);
        $this->frequency = $this->timeToArray($frequency);*/
    }

    public function generateFeed()
    {

        if ($this->enabled) {
            try {
                $data = Mage::getModel('doofinder_feed/cron');
                $offset = $data->load('offset');

                $lastRun = (int)$offset->getValue();


                $options = array(
                    '_limit_' => $this->stepSize,
                    '_offset_' => $lastRun,
                    'store_code' => $this->storeCode,
                    'grouped' => $this->_getBoolean($this->grouped),
                    // Calculate the minimal price with the tier prices
                    'minimal_price' => $this->_getBoolean($this->price),
                    // Not logged in by default
                    'customer_group_id' => 0,
                );

                $generator = Mage::getSingleton('doofinder_feed/generator', $options);
                $xmlData = $generator->run($options);

                if ($xmlData) {
                    $dir = Mage::getBaseDir('media').DS.'doofinder';
                    $path = Mage::getBaseDir('media').DS.'doofinder'.DS.$this->xmlPath;

                    // If directory doesn't exist create one
                    if (!file_exists($dir)) {
                        $this->_createDirectory($dir);
                    }

                    // If file can not be save throw an error
                    if (!$success = file_put_contents($path, $xmlData)) {
                        throw new Exception("File can not be saved: {$path}");
                    }

                    $newRun = $lastRun + (int)$this->stepSize;
                    $offset->setData('value', $newRun)->save();
                } else {
                    $offset->setData('value', '0')->save();
                }
            } catch (Exception $e) {
                Mage::errorLog('Exception: '.$e);
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
