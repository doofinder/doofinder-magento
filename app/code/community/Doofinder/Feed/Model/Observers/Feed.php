<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.32
 */

class Doofinder_Feed_Model_Observers_Feed
{

    protected $_config;

    protected $_storeCode;

    protected $_productCount;

    /**
     * @var Doofinder_Feed_Helper_Log
     */
    protected $_log;

    /**
     * Initialize log
     */
    public function __construct()
    {
        $this->_log = Mage::helper('doofinder_feed/log');
    }

    /**
     * Update product index in given store context
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @param Mage_Catalog_Model_Product $product
     * @param string $storeCode
     */
    protected function updateProductIndex($product, $storeCode)
    {
        $helper = Mage::helper('doofinder_feed');

        // Set store code
        $this->_storeCode = $storeCode;

        // Get store config
        $this->_config = $helper->getStoreConfig($this->_storeCode);

        // Set options
        $options = array(
            'close_empty' => true, // close xml even if there are no items
            'products' => array($product->getId()), // list of products in feed
            'store_code' => $this->_config['storeCode'],
            'grouped' => $this->_getBoolean($this->_config['grouped']),
            'display_price' => $this->_getBoolean($this->_config['display_price']),
            'minimal_price' => $this->_getBoolean('minimal_price', false),
            'image_size' => $this->_config['image_size'],
            'customer_group_id' => 0,
        );

        $generator = Mage::getModel('doofinder_feed/generator', $options);

        $this->_log->debugEnabled && $this->_log->debug(
            sprintf('Starting atomic update for product %d in store %s', $product->getId(), $storeCode)
        );

        $xmlData = $generator->run();

        if ($xmlData) {
            $rss = simplexml_load_string($xmlData);

            $hashId = Mage::getStoreConfig('doofinder_search/internal_settings/hash_id', $this->_storeCode);
            if ($hashId === '') {
                $warning = sprintf(
                    'HashID is not set for the \'%s\' store view, ' .
                    'therefore, search indexes haven\'t been updated for ' .
                    'this store view. To fix this problem set HashID for ' .
                    'a given stor view or disable Internal Search in ' .
                    'Doofinder Search Configuration.',
                    $this->_storeCode
                );
                $this->_log->debug($warning);
                Mage::getSingleton('adminhtml/session')->addWarning($warning);
                return;
            }

            $searchEngine = Mage::helper('doofinder_feed/search')->getDoofinderSearchEngine($this->_storeCode);

            // Check if search engine exists and skip foreach iteration if not.
            if (!$searchEngine) {
                $warning = sprintf(
                    'Search engine with HashID %s doesn\'t exists. ' .
                    'Please, check your configuration.',
                    $hashId
                );
                $this->_log->debug($warning);
                Mage::getSingleton('adminhtml/session')->addWarning($warning);
                return;
            }

            // Declare array of products to update
            $products = array();
            foreach ($rss->channel->item as $item) {
                $_product = array();
                foreach ($item as $key => $value) {
                    $_product[$key] = (string)$value;
                }

                $products[] = $_product;
            }

            if (!empty($products)) {
                $searchEngine->updateItems('product', $products);
                $this->_log->debugEnabled && $this->_log->debug(
                    sprintf(
                        'Atomic update for product %d in store %s done with: %s',
                        $product->getId(),
                        $storeCode,
                        json_encode($products)
                    )
                );
                return;
            }

            $this->_log->debugEnabled && $this->_log->debug(
                sprintf('Atomic update for product %d in store %s failed with no data', $product->getId(), $storeCode)
            );
        }
    }

    public function updateSearchEngineIndexes($observer)
    {
        $helper = Mage::helper('doofinder_feed');

        $product = $observer->getProduct();

        $storeCodes = array();
        $store = Mage::getModel('core/store')->load($product->getStoreId());

        // If current store is admin then get an array of all possible stores for a website
        if ($store->getCode() !== 'admin') {
            $storeCodes[] = $store->getCode();
        } else {
            foreach (Mage::app()->getStores() as $store) {
                $storeCodes[] = $store->getCode();
            }
        }

        // Filter out disabled stores
        foreach (array_keys($storeCodes) as $key) {
            $storeCode = $storeCodes[$key];

            $engineEnabled = Mage::getStoreConfig(
                'doofinder_search/internal_settings/enable',
                $storeCode
            );
            $atomicUpdatesEnabled = Mage::getStoreConfig(
                'doofinder_cron/feed_settings/atomic_updates_enabled',
                $storeCode
            );

            if (!$engineEnabled || !$atomicUpdatesEnabled) {
                unset($storeCodes[$key]);
            }
        }

        // Terminate updates where there is no store enabled
        if (empty($storeCodes)) return;

        // Loop over all stores and update relevant search engines
        foreach ($storeCodes as $storeCode) {
            try {
                $this->updateProductIndex($product, $storeCode);
            } catch (Exception $e) {
                $warning = $helper->__(
                    'There was an error during product %d indexing: %s',
                    $product->getId(),
                    $e->getMessage()
                );
                $this->_log->debug($warning);
                Mage::getSingleton('adminhtml/session')->addWarning($warning);
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
            $this->_log->debugEnabled && $this->_log->debug(
                sprintf('Locking cron process for store %s', $process->getStoreCode())
            );

            if ($helper->fileExists($lockFilepath)) {
                Mage::throwException($helper->__('Process for store %s is already locked', $process->getStoreCode()));
            }

            // @codingStandardsIgnoreStart
            touch($lockFilepath);
            // @codingStandardsIgnoreEnd
        } else {
            $this->_log->debugEnabled && $this->_log->debug(
                sprintf('Unlocking cron process for store %s locked', $process->getStoreCode())
            );

            $helper->fileRemove($lockFilepath);
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

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    // @codingStandardsIgnoreStart
    public function generateFeed($observer)
    {
    // @codingStandardsIgnoreEnd
        $helper = Mage::helper('doofinder_feed');
        $date = Mage::getSingleton('core/date');

        // Get doofinder process model
        $collection = Mage::getModel('doofinder_feed/cron')->getCollection();
        $collection
            ->addFieldToFilter('status', array('in' => array($helper::STATUS_PENDING, $helper::STATUS_RUNNING)))
            ->addFieldToFilter(
                'next_iteration',
                array(
                    'lteq' => $helper->getScheduledAt(
                        array(
                            // @codingStandardsIgnoreStart
                            $date->date('H') + $helper->getTimezoneOffset(),
                            $date->date('i'),
                            $date->date('s')
                            // @codingStandardsIgnoreEnd
                        )
                    )
                )
            )
            ->setOrder('next_iteration', 'asc');
        // @codingStandardsIgnoreStart
        $collection->getSelect()->limit(1);
        // @codingStandardsIgnoreEnd

        $process = $collection->fetchItem();

        if (!$process || !$process->getId()) {
            $this->_log->debug('No active cron processes');
            return;
        }

        $this->_log->debugEnabled && $this->_log->debug(
            sprintf('Starting cron process for store %s', $process->getStoreCode())
        );

        try {
            // Lock process
            $this->lockProcess($process);

            // Get store code
            $this->_storeCode = $process->getStoreCode();

            // Set store context
            Mage::app()->setCurrentStore($this->_storeCode);

            // Get store config
            $this->_config = $helper->getStoreConfig($this->_storeCode);

            // Clear out the message
            $process->setMessage($helper::MSG_EMPTY);

            // Get current offset
            $offset = (int) $process->getOffset();

            // Get step size
            $stepSize = (int) $this->_config['stepSize'];

            // Set paths
            $path = $helper->getFeedPath($this->_storeCode);
            $tmpPath = $helper->getFeedTemporaryPath($this->_storeCode);

            $this->_log->debugEnabled && $this->_log->debug(
                sprintf('Feed path for store %s: ', $process->getStoreCode(), $path)
            );
            $this->_log->debugEnabled && $this->_log->debug(
                sprintf('Temporary feed path for store %s: ', $process->getStoreCode(), $path)
            );

            // Set options for cron generator
            $options = array(
                '_limit_' => $stepSize,
                '_offset_' => $offset,
                'store_code' => $this->_config['storeCode'],
                'grouped' => $this->_getBoolean($this->_config['grouped']),
                'display_price' => $this->_getBoolean($this->_config['display_price']),
                'minimal_price' => $this->_getBoolean('minimal_price', false),
                'image_size' => $this->_config['image_size'],
                'customer_group_id' => 0,
            );

            $generator = Mage::getModel('doofinder_feed/generator', $options);


            try {
                $xmlData = $generator->run();
            } catch (Exception $e) {
                $this->_log->debugEnabled && $this->_log->debug(
                    sprintf(
                        'Generator run failed with exception "%s" and following errors: %s',
                        $e->getMessage(),
                        json_encode($generator->getErrors())
                    )
                );
                throw $e;
            }

            // If there were errors log them
            if ($errors = $generator->getErrors()) {
                $process->setErrorStack($process->getErrorStack() + count($errors));

                foreach ($errors as $error) {
                    $this->_log->log($process, Doofinder_Feed_Helper_Log::ERROR, $error, false);
                }
            }

            $message = $helper->__(
                'Processed products with ids in range %d - %d',
                $offset + 1,
                $generator->getLastProcessedProductId()
            );
            $this->_log->log($process, Doofinder_Feed_Helper_Log::STATUS, $message);

            // If there is new data append to xml.tmp else convert into xml
            if ($xmlData) {
                $dir = Mage::getBaseDir('media').DS.'doofinder';

                // If directory doesn't exist create one
                if (!$helper->fileExists($dir)) {
                    $helper->createFeedDirectory($dir);
                }

                // If file can not be save throw an error
                if (!$helper->fileAppend($tmpPath, $xmlData)) {
                    Mage::throwException($helper->__("File can not be saved: {$tmpPath}"));
                }

                $this->_productCount = $generator->getProductCount();
            } else {
                $this->_log->log($process, Doofinder_Feed_Helper_Log::WARNING, $helper->__('No data added to feed'));
            }

            // Set process offset and progress
            $process->setOffset($generator->getLastProcessedProductId());
            $process->setComplete(sprintf('%0.1f%%', $generator->getProgress() * 100));

            if (!$generator->isFeedDone()) {
                $helper->createNewSchedule($process);
            } else {
                $this->_log->log($process, Doofinder_Feed_Helper_Log::STATUS, $helper->__('Feed generation completed'));

                if (!$helper->fileMove($tmpPath, $path)) {
                    Mage::throwException($helper->__("Cannot rename {$tmpPath} to {$path}"));
                }

                $process->setMessage($helper->__('Last process successfully completed. Now waiting for new schedule.'));
                $this->_endProcess($process);
            }
        } catch (Exception $e) {
            $this->_log->log($process, Doofinder_Feed_Helper_Log::ERROR, $e->getMessage());
            $process->setErrorStack($process->getErrorStack() + 1);
            $process->setMessage('#error#' . $e->getMessage());
            $helper->createNewSchedule($process);
        }

        // Unlock process
        $this->unlockProcess($process);
    }

    /**
     * Cast any value to bool
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
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

        $true = array('true', 'on', 'yes');
        $false  = array('false', 'off', 'no');

        if (in_array($value, $true))
            return true;

        if (in_array($value, $false))
            return false;

        return $defaultValue;
    }


    /**
     * Converts time string into array.
     * @param string $time
     * @return array
     */
    protected function timeToArray($time = null)
    {
        // Declare new time
        $newTime;
        // Validate $time variable
        if (!$time || !is_string($time) || substr_count($time, ',') < 2) {
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
    protected function _endProcess(Doofinder_Feed_Model_Cron $process)
    {
        $helper = Mage::helper('doofinder_feed');
        // Prepare data
        $data = array(
            'status'    =>  $helper::STATUS_WAITING,
            'next_run' => '-',
            'next_iteration' => '-',
            'last_feed_name' => $this->_config['xmlName'],
            'schedule_id' => null,
        );

        $process->addData($data)->save();
    }


    public function addButtons($observer)
    {
        $block = $observer->getBlock();

        if ($block instanceof Mage_Adminhtml_Block_System_Config_Edit
            && $block->getRequest()->getParam('section') == 'doofinder_cron'
        ) {
            $html = $block->getChild('save_button')->toHtml();

            $html .= $block->getLayout()->createBlock('doofinder_feed/adminhtml_widget_button_reschedule')->toHtml();

            $block->setChild(
                'save_button',
                $block->getLayout()->createBlock('core/text')->setText($html)
            );
        }
    }



}
