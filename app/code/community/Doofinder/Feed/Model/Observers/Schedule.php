<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.28
 */

class Doofinder_Feed_Model_Observers_Schedule
{

    /**
     * Register missing / reset schedules after configuration saves.
     *
     * @param Varien_Event_Observer $observer
     */
    public function saveNewSchedule($observer)
    {
        // Get store code
        $currentStoreCode = $observer->getStore();

        // Stores array holding all store codes
        $codes = array();

        // Create stores codes array
        if ($currentStoreCode) {
            $codes[] = $currentStoreCode;
        } else {
            $stores = Mage::app()->getStores();
            foreach ($stores as $store) {
                if ($store->getIsActive()) {
                    $codes[] = $store->getCode();
                }
            }
        }

        // Check if user wants to reset the schedule
        $reset = (bool) Mage::app()->getRequest()->getParam('reset');

        foreach ($codes as $storeCode) {
            $this->updateProcess($storeCode, $reset, $reset);
        }
    }

    /**
     * Regenerate finished shcedules.
     *
     * @param Varien_Event_Observer $observer
     */
    public function regenerateSchedule()
    {
        // Get store
        $stores = Mage::app()->getStores();

        foreach ($stores as $store) {
            if ($store->getIsActive()) {
                $this->updateProcess($store->getCode());
            }
        }
    }

    /**
     * Gets process for given store code
     *
     * @param string $storeCode
     * @return Doofinder_Feed_Model_Cron
     */
    protected function _getProcessByStoreCode($storeCode = 'default')
    {
        $process = Mage::getModel('doofinder_feed/cron')->load($storeCode, 'store_code');
        return $process->getId() ? $process : null;
    }

    /**
     * Update process for given store code.
     * If process does not exits - create it.
     * Reschedule the process if it needs it.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @param Doofinder_Feed_Model_Cron $process
     * @param boolean $reset
     * @param boolean $now
     * @param boolean $force
     */
    public function updateProcess($storeCode = 'default', $reset = false, $now = false, $force = false)
    {
        // Get store
        $helper = Mage::helper('doofinder_feed');
        $config = $helper->getStoreConfig($storeCode);
        $store = Mage::getModel('core/store')->load($storeCode);
        $date = Mage::getSingleton('core/date');

        // Override time if $now is enabled
        if ($now) {
            $config['time'] = array(
                // @codingStandardsIgnoreStart
                $date->date('H') + $helper->getTimezoneOffset(),
                $date->date('i'),
                $date->date('s')
                // @codingStandardsIgnoreEnd
            );
        }

        $isEnabled = (bool) $config['enabled'];

        // Try loading store process
        $process = $this->_getProcessByStoreCode($storeCode);

        // Create new process if it not exists
        if (!$process) {
            $process = $this->_registerProcess($storeCode);
        }

        // Enable/disable process if it needs to
        if ($isEnabled || $force) {
            if ($process->getStatus() == $helper::STATUS_DISABLED) {
                $this->_enableProcess($process);
            }
        } else {
            if ($process->getStatus() != $helper::STATUS_DISABLED) {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $helper->__('Process for store "%s" has been disabled', $store->getName())
                );
                $this->_removeTmpXml($storeCode);
                $this->_disableProcess($process);
            }

            return $this;
        }

        // Do not process the schedule if it has insufficient file permissions
        if (!$this->_checkFeedFilePermission($storeCode)) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__(
                    'Insufficient file permissions for store: %s. ' .
                    'Check if the feed file is writeable',
                    $store->getName()
                )
            );
            return $this;
        }

        // Reschedule the process if it needs to
        if ($reset || $process->getStatus() == $helper::STATUS_WAITING) {
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $helper->__('Process for store "%s" has been rescheduled', $store->getName())
            );
            $this->_removeTmpXml($storeCode);
            $this->_rescheduleProcess($config, $process);
        }
    }

    /**
     * Register a new process
     *
     * @return Doofinder_Feed_Model_Cron
     */
    protected function _registerProcess($storeCode = 'default')
    {
        $helper = Mage::helper('doofinder_feed');
        $config = $helper->getStoreConfig($storeCode);
        if (empty($status)) {
            $status = $config['enabled'] ? $helper::STATUS_WAITING : $helper::STATUS_DISABLED;
        }

        $data = array(
            'store_code'    =>  $storeCode,
            'status'        =>  $status,
            'message'       =>  $helper::MSG_EMPTY,
            'complete'      =>  '-',
            'next_run'      =>  '-',
            'next_iteration'=>  '-',
            'last_feed_name'=>  'None',
        );
        $process = Mage::getModel('doofinder_feed/cron')->setData($data)->save();

        Mage::helper('doofinder_feed/log')->log(
            $process,
            Doofinder_Feed_Helper_Log::STATUS,
            $helper->__('Process has been registered')
        );

        return $process;
    }

    /**
     * Enable the process
     *
     * @param Doofinder_Feed_Model_Cron $process
     */
    protected function _enableProcess(Doofinder_Feed_Model_Cron $process)
    {
        $helper = Mage::helper('doofinder_feed');
        $process->setStatus($helper::STATUS_WAITING)->save();
        Mage::helper('doofinder_feed/log')->log(
            $process,
            Doofinder_Feed_Helper_Log::STATUS,
            $helper->__('Process has been enabled')
        );
    }

    /**
     * Disable the process
     *
     * @param Doofinder_Feed_Model_Cron $process
     */
    protected function _disableProcess(Doofinder_Feed_Model_Cron $process)
    {
        $helper = Mage::helper('doofinder_feed');
        $process->setStatus($helper::STATUS_DISABLED)->save();
        Mage::helper('doofinder_feed/log')->log(
            $process,
            Doofinder_Feed_Helper_Log::STATUS,
            $helper->__('Process has been disabled')
        );
    }

    /**
     * Remove tmp xml file.
     *
     * @param string $store_code
     * @return bool
     */
    protected function _removeTmpXml($storeCode = null)
    {
        if (empty($storeCode)) {
            return false;
        }

        $helper = Mage::helper('doofinder_feed');
        $config = $helper->getStoreConfig($storeCode);
        $filePath = Mage::getBaseDir('media').DS.'doofinder'.DS.$config['xmlName'].'.tmp';
        if ($helper->fileExists($filePath)) {
            $success = $helper->fileRemove($filePath);
            if ($success) {
                Mage::getSingleton('core/session')->addSuccess("Temporary xml file: {$filePath} has beed removed.");
                return true;
            } else {
                Mage::getSingleton('core/session')->addError(
                    "Could not remove {$filePath}; This can lead to some errors. " .
                    "Remove this file manually."
                );
                return false;
            }
        }

        return false;
    }

    /**
     * Validate file permissions for feed generation.
     *
     * @return boolean
     */
    protected function _checkFeedFilePermission($storeCode)
    {
        $helper = Mage::helper('doofinder_feed');

        try {
            $helper->createFeedDirectory();
        } catch (Exception $e) {
            return false;
        }

        $dir = $helper->getFeedDirectory();
        $path = $helper->getFeedPath($storeCode);
        $tmpPath = $helper->getFeedTemporaryPath($storeCode);

        $fileIo = new Varien_Io_File();
        return $fileIo->isWriteable($dir)
               && (!$fileIo->fileExists($path) || $fileIo->isWriteable($path))
               && (!$fileIo->fileExists($tmpPath) || $fileIo->isWriteable($tmpPath));
    }

    /**
     * Reschedule the process accordingly to process configuration.
     *
     * @param array $storeConfig
     * @param Doofinder_Feed_Model_Cron $process
     */
    protected function _rescheduleProcess($config, Doofinder_Feed_Model_Cron $process)
    {
        $helper = Mage::helper('doofinder_feed');

        $timescheduled = $helper->getScheduledAt($config['time'], $config['frequency']);

        $process->setStatus($helper::STATUS_PENDING)
            ->setComplete('0%')
            ->setNextRun($timescheduled)
            ->setNextIteration($timescheduled)
            ->setOffset(0)
            ->setMessage($helper::MSG_PENDING)
            ->setErrorStack(0)
            ->save();

        Mage::helper('doofinder_feed/log')->log(
            $process,
            Doofinder_Feed_Helper_Log::STATUS,
            $helper->__('Process has been scheduled')
        );
    }
}
