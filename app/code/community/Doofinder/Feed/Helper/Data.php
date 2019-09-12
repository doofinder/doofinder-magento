<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Helpers
 * @package    Doofinder_Feed
 * @version    1.8.31
 */

/**
 * Data helper for Doofinder Feed
 *
 * @version    1.8.31
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CRON_DAILY     =    Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_DAILY;
    const CRON_WEEKLY    =    Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY;
    const CRON_MONTHLY   =    Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY;

    /**
     * Panel info messages.
     */
    const STATUS_DISABLED    = 'Disabled';
    const STATUS_PENDING    = Mage_Cron_Model_Schedule::STATUS_PENDING;
    const STATUS_RUNNING    = Mage_Cron_Model_Schedule::STATUS_RUNNING;
    const STATUS_SUCCESS    = Mage_Cron_Model_Schedule::STATUS_SUCCESS;
    const STATUS_MISSED     = Mage_Cron_Model_Schedule::STATUS_MISSED;
    const STATUS_WAITING     = 'Waiting...';
    const STATUS_ERROR      = Mage_Cron_Model_Schedule::STATUS_ERROR;
    const JOB_CODE          = 'doofinder_feed_generate';

    const MSG_EMPTY = "Currently there is no message.";
    const MSG_PENDING = "The new process of generating the feed has been registered and it's waiting to be activated.";
    const MSG_DISABLED = "The feed generator for this view is currently disabled.";
    const MSG_WAITING = "Waiting for registering the new process of generating the feed.";

    /**
     * @var Varien_Io_File
     */
    private $ioFile = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->ioFile = new Varien_Io_File();
    }

    /**
     * Get product price
     *
     * @param Magento_Catalog_Model_Product $product
     * @param string $attribute = 'price'
     * @param boolean|null $tax = null
     * @return float
     */
    public function getProductPrice(Mage_Catalog_Model_Product $product, $attribute = 'price', $tax = null)
    {
        $taxHelper = Mage::helper('tax');

        switch ($attribute) {
            case 'price':
                $price = $product->getData('price');
                break;

            case 'sale_price':
                $salePrice = $product->getPriceModel()->getFinalPrice(null, $product);
                $price = $product->getData('price') <= $salePrice ? null : $salePrice;
                break;
            case 'tier_price':
                $prices = $product->getTierPrice();
                if (empty($prices)) {
                    $price = null;
                    break;
                }
                $price = min(
                    array_filter(
                        array_map(function($item) {
                            return $item['all_groups'] == 1 ? $item['price'] : null;
                        }, $prices)
                    )
                );
                break;

            default:
                $price = null;
        }

        if ($tax === null) {
            $tax = $taxHelper->getPriceDisplayType() != Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX;
        }

        // Return raw price with/without tax - no price rounding here
        return $taxHelper->getPrice($product, $price, $tax, null, null, null, null, null, false);
    }

    /**
     * Gets store config for cron settings.
     * @param string $storeCode
     * @param boolean $withPassword = true
     * @return array
     */
    public function getStoreConfig($storeCode = '', $withPassword = true)
    {
        $xmlName = Mage::getStoreConfig('doofinder_cron/schedule_settings/name', $storeCode);
        $config = array(
            'enabled'   =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/enabled', $storeCode),
            'display_price'     =>  Mage::getStoreConfig('doofinder_cron/feed_settings/display_price', $storeCode),
            'grouped'   =>  Mage::getStoreConfig('doofinder_cron/feed_settings/grouped', $storeCode),
            'image_size'     =>  Mage::getStoreConfig('doofinder_cron/feed_settings/image_size', $storeCode),
            'stepSize'  =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/step', $storeCode),
            'stepDelay' =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/delay', $storeCode),
            'frequency' =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/frequency', $storeCode),
            'time'      =>  explode(',', Mage::getStoreConfig('doofinder_cron/schedule_settings/time', $storeCode)),
            'storeCode' =>  $storeCode,
            'xmlName'   =>  $this->_processXmlName($xmlName, $storeCode, $withPassword),
            'reset'     =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/reset', $storeCode),
        );
        return $config;
    }

    /**
     * Process xml filename
     * @param string $name = 'doofinder-{store_code}.xml'
     * @param string $code = 'default'
     * @param string|boolean $password = true
     * @return bool
     */
    protected function _processXmlName($name = 'doofinder-{store_code}.xml', $code = 'default', $password = true)
    {
        $pattern = '/\{\s*store_code\s*\}/';

        if ($password === true) {
            $password = Mage::getStoreConfig('doofinder_cron/feed_settings/password', $code);
        }

        $replacement = $code;
        if ($password) {
            $replacement .= '-' . $password;
        }

        $newName = preg_replace($pattern, $replacement, $name);
        return $newName;
    }

    /**
     * Change feed file password
     *
     * @param string $storeCode
     * @param string $oldPassword
     * @param string $newPassword
     */
    public function changeXmlPassword($storeCode, $oldPassword, $newPassword)
    {
        $xmlName = Mage::getStoreConfig('doofinder_cron/schedule_settings/name', $storeCode);
        $dir = $this->getFeedDirectory();

        $oldFilepath = $dir . DS . $this->_processXmlName($xmlName, $storeCode, $oldPassword);
        $newFilename = $this->_processXmlName($xmlName, $storeCode, $newPassword);
        $newFilepath = $dir . DS . $newFilename;

        if ($this->fileExists($oldFilepath)) {
            if (!$this->fileExists($newFilepath) && !$this->fileMove($oldFilepath, $newFilepath)) {
                $msg = __(
                    'Feed file could not be renamed accordingly to new %s ' .
                    'value because file permission issues or file with name %s already exists.',
                    $this->getData('field_config/label'),
                    $newFilename
                );
                throw new \Magento\Framework\Exception\LocalizedException($msg);
            }

            $process = Mage::getModel('doofinder_feed/cron')->load($storeCode, 'store_code');
            if ($process->getId()) {
                $process
                    ->setLastFeedName($newFilename)
                    ->save();
            }
        }
    }

    /**
     * Creates new schedule entry.
     * @param Doofinder_Feed_Model_Cron $process
     */
    public function createNewSchedule(Doofinder_Feed_Model_Cron $process)
    {
        $helper = Mage::helper('doofinder_feed');
        $date = Mage::getSingleton('core/date');

        $config = $helper->getStoreConfig($process->getStoreCode());

        // Set new schedule time
        $delayInMin = (int) $config['stepDelay'];
        // @codingStandardsIgnoreStart
        $timestamp = mktime(
            $date->date("H"),
            $date->date("i") + $delayInMin,
            $date->date("s"),
            $date->date("m"),
            $date->date("d"),
            $date->date("Y")
        );
        $timescheduled = $date->date(null,  $timestamp);
        // @codingStandardsIgnoreEnd

        // Prepare new process data
        $status = $helper::STATUS_RUNNING;

        // Set process data and save
        $process->setStatus($status)
            ->setNextRun('-')
            ->setNextIteration($timescheduled)
            ->save();

        Mage::helper('doofinder_feed/log')->log(
            $process,
            Doofinder_Feed_Helper_Log::STATUS,
            $helper->__('Scheduling the next step for %s', $timescheduled)
        );
    }

    public function getScheduledAt($time = null, $frequency = null, $timezoneOffset = true)
    {
        $date = Mage::getSingleton('core/date');
        // @codingStandardsIgnoreStart
        $parts = array($time[0], $time[1], $time[2], $date->date('m'), $date->date('d'));
        // @codingStandardsIgnoreEnd
        $offset = $this->getTimezoneOffset();

        $now = $date->timestamp();
        $start = mktime($parts[0] - $offset, $parts[1], $parts[2], $parts[3], $parts[4]);

        if ($start < $now) {
            switch ($frequency) {
                case self::CRON_MONTHLY:
                    $parts[3] += 1;
                    break;

                case self::CRON_WEEKLY:
                    $parts[4] += 7;
                    break;

                case self::CRON_DAILY:
                    $parts[4] += 1;
                    break;
            }
        }

        if ($timezoneOffset) {
            $parts[0] -= $offset;
        }

        // @codingStandardsIgnoreStart
        return $date->date(null, mktime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4]));
        // @codingStandardsIgnoreEnd
    }

    public function getTimezoneOffset()
    {
        $timezone = Mage::getStoreConfig('general/locale/timezone');
        $backTimezone = date_default_timezone_get();
        // Set relative timezone
        date_default_timezone_set($timezone);
        // @codingStandardsIgnoreStart
        $offset = (Mage::getSingleton('core/date')->date('Z') / 60 / 60);
        // @codingStandardsIgnoreEnd
        // Revoke server timezone
        date_default_timezone_set($backTimezone);
        return $offset;
    }

    /**
     * Get path to feed file.
     *
     * @return string
     */
    public function getFeedDirectory()
    {
        return Mage::getBaseDir('media').DS.'doofinder';
    }

    /**
     * Get path to feed file.
     *
     * @return string
     */
    public function getFeedPath($storeCode)
    {
        $config = $this->getStoreConfig($storeCode);

        return $this->getFeedDirectory().DS.$config['xmlName'];
    }

    /**
     * Get path to feed file.
     *
     * @return string
     */
    public function getFeedTemporaryPath($storeCode)
    {
        return $this->getFeedPath($storeCode) . '.tmp';
    }

    /**
     * Get path to feed lock file.
     *
     * @return string
     */
    public function getFeedLockPath($storeCode)
    {
        return $this->getFeedPath($storeCode) . '.lock';
    }

    /**
     * Creates feed directory.
     *
     * @param string $dir
     * @return bool
     */
    public function createFeedDirectory()
    {
        $dir = $this->getFeedDirectory();
        $this->mkdir($dir);

        return true;
    }

    /**
     * Check if file exists
     *
     * @param string $filepath
     * @return boolean
     */
    public function fileExists($filepath)
    {
        return $this->ioFile->fileExists($filepath);
    }

    /**
     * Move file
     *
     * @param string $src
     * @param string $dest
     * @return boolean
     */
    public function fileMove($src, $dest)
    {
        return $this->ioFile->mv($src, $dest);
    }

    /**
     * Remove file
     *
     * @param string $filepath
     * @return boolean
     */
    public function fileRemove($filepath)
    {
        return $this->ioFile->rm($filepath);
    }

    /**
     * Append content to file
     *
     * @param string $filepath
     * @param string $content
     * @return boolean
     */
    public function fileAppend($filepath, $content)
    {
        return file_put_contents($filepath, $content, FILE_APPEND | LOCK_EX);
    }

    /**
     * Create directory
     *
     * @param string $dir
     * @return boolean
     */
    public function mkdir($dir)
    {
        return $this->ioFile->checkAndCreateFolder($dir, 0777);
    }
}
