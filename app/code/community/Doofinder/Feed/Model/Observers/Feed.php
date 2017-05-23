<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Model_Observers_Feed
{

    private $config;

    private $storeCode;

    private $productCount;


    public function updateSearchEngineIndexes($observer) {

        $helper = Mage::helper('doofinder_feed');

        $product = $observer->getProduct();
        $products[] = $product->getId();

        $storeCodes = array();
        $store = Mage::getModel('core/store')->load($product->getStoreId());

        // If current store is admin then get an array of all possible stores for a website
        if ($store->getCode() !== 'admin') {
            $storeCodes[] = $store->getCode();
        } else {
            foreach(Mage::app()->getStores() as $store) {
                $storeCodes[] = $store->getCode();
            }
        }

        // Filter out disabled stores
        foreach (array_keys($storeCodes) as $key) {
            $storeCode = $storeCodes[$key];

            $engineEnabled = Mage::getStoreConfig('doofinder_search/internal_settings/enable', $storeCode);
            $atomicUpdatesEnabled = Mage::getStoreConfig('doofinder_cron/feed_settings/atomic_updates_enabled', $storeCode);

            if (!$engineEnabled || !$atomicUpdatesEnabled) {
                unset($storeCodes[$key]);
            }
        }

        // Terminate updates where there is no store enabled
        if (empty($storeCodes)) return;

        // Loop over all stores and update relevant search engines
        foreach ($storeCodes as $storeCode) {
            // Set store code
            $this->storeCode = $storeCode;

            // Get store config
            $this->config = $helper->getStoreConfig($this->storeCode);



            // Set options
            $options = array(
                'close_empty' => true, // close xml even if there are no items
                'products' => $products, // list of products in feed
                'store_code' => $this->config['storeCode'],
                'grouped' => $this->_getBoolean($this->config['grouped']),
                'display_price' => $this->_getBoolean($this->config['display_price']),
                'minimal_price' => $this->_getBoolean('minimal_price', false),
                'image_size' => $this->config['image_size'],
                'customer_group_id' => 0,
            );

            $generator = Mage::getModel('doofinder_feed/generator', $options);

            $xmlData = $generator->run();

            if ($xmlData) {
                $rss = simplexml_load_string($xmlData);

                $hashId = Mage::getStoreConfig('doofinder_search/internal_settings/hash_id', $this->storeCode);
                if ($hashId === '') {

                    $warning = sprintf('HashID is not set for the \'%s\' store view, therefore, search indexes haven\'t been
                    updated for
                    this store view. To fix this problem set HashID for a given stor view or disable Internal Search in Doofinder
                    Search Configuration.', $this->storeCode);
                    Mage::getSingleton('adminhtml/session')->addWarning($warning);
                    continue;
                }

                $searchEngine = Mage::helper('doofinder_feed/search')->getDoofinderSearchEngine($this->storeCode);

                // Check if search engine exists and skip foreach iteration if not.
                if (!$searchEngine) {
                    $error = sprintf('Search engine with HashID %s doesn\'t exists. Please, check your configuration.', $hashId);
                    Mage::getSingleton('adminhtml/session')->addError($error);
                    continue;
                }

                // Declare array of products to update
                $products = array();
                foreach ($rss->channel->item as $item) {
                    $product = array();
                    foreach ($item as $key => $value) {
                        $product[$key] = (string)$value;
                    }
                    $products[] = $product;
                }
                if (count($products))
                    $searchEngine->updateItems('product', $products);

            }
        }


    }

    /**
     * Lock process
     *
     * Locking process ensures that no other
     * cron job runs it at the same time
     *
     * @param Doofinder_Feed_Model_Cron
     * @param boolean $remove = false - Should the lock be removed instead of created
     */
    protected function lockProcess(Doofinder_Feed_Model_Cron $process, $remove = false)
    {
        $helper = Mage::helper('doofinder_feed');
        $lockFilepath = $helper->getFeedLockPath($process->getStoreCode());

        // Create lock file
        if (!$remove) {
            if (file_exists($lockFilepath)) {
                Mage::throwException($helper->__('Process for store %s is already locked', $process->getStoreCode()));
            }

            touch($lockFilepath);
        } else {
            unlink($lockFilepath);
        }
    }

    /**
     * Unlock process
     *
     * @param Doofinder_Feed_Model_Cron
     */
    protected function unlockProcess(Doofinder_Feed_Model_Cron $process)
    {
        return $this->lockProcess($process, true);
    }

    public function generateFeed($observer)
    {
        $stores = Mage::app()->getStores();
        $helper = Mage::helper('doofinder_feed');

        // Get doofinder process model
        $collection = Mage::getModel('doofinder_feed/cron')->getCollection();
        $collection
            ->addFieldToFilter('status', array('in' => array($helper::STATUS_PENDING, $helper::STATUS_RUNNING)))
            ->addFieldToFilter('next_iteration', array(
                'lteq' => $helper->getScheduledAt(array(date('H') + $helper->getTimezoneOffset(), date('i'), date('s')))
            ))
            ->setOrder('next_iteration', 'asc');
        $collection->getSelect()->limit(1);

        $process = $collection->fetchItem();

        if (!$process || !$process->getId()) {
            return;
        }

        // Lock process
        $this->lockProcess($process);

        // Get store code
        $this->storeCode = $process->getStoreCode();

        // Set store context
        Mage::app()->setCurrentStore($this->storeCode);

        // Get store config
        $this->config = $helper->getStoreConfig($this->storeCode);

        try {
            // Clear out the message
            $process->setMessage($helper::MSG_EMPTY);

            // Get current offset
            $offset = intval($process->getOffset());

            // Get step size
            $stepSize = intval($this->config['stepSize']);

            // Set paths
            $path = $helper->getFeedPath($this->storeCode);
            $tmpPath = $helper->getFeedTemporaryPath($this->storeCode);

            // Get job code
            $jobCode = $helper::JOB_CODE;

            // Set options for cron generator
            $options = array(
                '_limit_' => $stepSize,
                '_offset_' => $offset,
                'store_code' => $this->config['storeCode'],
                'grouped' => $this->_getBoolean($this->config['grouped']),
                'display_price' => $this->_getBoolean($this->config['display_price']),
                'minimal_price' => $this->_getBoolean('minimal_price', false),
                'image_size' => $this->config['image_size'],
                'customer_group_id' => 0,
            );

            $generator = Mage::getModel('doofinder_feed/generator', $options);

            $xmlData = $generator->run();

            // If there were errors log them
            if ($errors = $generator->getErrors()) {
                $process->setErrorStack($process->getErrorStack() + count($errors));

                foreach ($errors as $error) {
                    Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::ERROR, $error);
                }
            }

            $message = $helper->__('Processed products with ids in range %d - %d', $offset + 1, $generator->getLastProcessedProductId());
            Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::STATUS, $message);

            // If there is new data append to xml.tmp else convert into xml
            if ($xmlData) {
                $dir = Mage::getBaseDir('media').DS.'doofinder';

                // If directory doesn't exist create one
                if (!file_exists($dir)) {
                    $helper->createFeedDirectory($dir);
                }

                // If file can not be save throw an error
                if (!$success = file_put_contents($tmpPath, $xmlData, FILE_APPEND | LOCK_EX)) {
                    Mage::throwException($helper->__("File can not be saved: {$tmpPath}"));
                }

                $this->productCount = $generator->getProductCount();
            } else {
                Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::WARNING, $helper->__('No data added to feed'));
            }

            // Set process offset and progress
            $process->setOffset($generator->getLastProcessedProductId());
            $process->setComplete(sprintf('%0.1f%%', $generator->getProgress() * 100));

            if (!$generator->isFeedDone()) {
                $helper->createNewSchedule($process);
            } else {
                Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::STATUS, $helper->__('Feed generation completed'));

                if (!rename($tmpPath, $path)) {
                    Mage::throwException($helper->__("Cannot rename {$tmpPath} to {$path}"));
                }

                $process->setMessage($helper->__('Last process successfully completed. Now waiting for new schedule.'));
                $this->_endProcess($process);
            }

        } catch (Exception $e) {
            Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::ERROR, $e->getMessage());
            $process->setErrorStack($process->getErrorStack() + 1);
            $process->setMessage('#error#' . $e->getMessage());
            $helper->createNewSchedule($process);
        }

        // Unlock process
        $this->unlockProcess($process);
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
     * Concludes process.
     * @param Doofinder_Feed_Model_Cron $process
     */
    private function _endProcess(Doofinder_Feed_Model_Cron $process) {
        $helper = Mage::helper('doofinder_feed');
        // Prepare data
        $data = array(
            'status'    =>  $helper::STATUS_WAITING,
            'next_run' => '-',
            'next_iteration' => '-',
            'last_feed_name' => $this->config['xmlName'],
            'schedule_id' => null,
        );

        $process->addData($data)->save();
    }


    public function addButtons($observer) {
        $block = $observer->getBlock();

        if ($block instanceof Mage_Adminhtml_Block_System_Config_Edit && $block->getRequest()->getParam('section') == 'doofinder_cron') {
            $html = $block->getChild('save_button')->toHtml();

            $html .= $block->getLayout()->createBlock('doofinder_feed/adminhtml_widget_button_reschedule')->toHtml();

            $block->setChild('save_button',
                $block->getLayout()->createBlock('core/text')->setText($html)
            );
        }
    }



}
