<?php

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
                $codes[] = $store->getCode();
            }
        }

        foreach ($codes as $storeCode) {
            // Get store
            $store = Mage::app()->getStore($storeCode);
            $helper = Mage::helper('doofinder_feed');
            $config = $helper->getStoreConfig($storeCode);
            $resetSchedule = (bool) Mage::app()->getRequest()->getParam('reset');
            $isEnabled = (bool) $config['enabled'];

            // Do not process the schedule if it has insufficient file permissions
            if (!$this->_checkFeedFilePermission($storeCode)) {
                Mage::getSingleton('adminhtml/session')->addError($helper->__('Insufficient file permissions for store: %s. Check if the feed file is writeable', $store->getName()));
                continue;
            }

            // Register process if not exists
            if (!$this->_isProcessRegistered($storeCode)) {
                $status = $isEnabled? $helper::STATUS_WAITING : $helper::STATUS_DISABLED;
                if ($resetSchedule) {
                    $status = $helper::STATUS_PENDING;
                }

                $this->_registerProcess($storeCode, $status);
            }

            $process = Mage::getModel('doofinder_feed/cron')->load($storeCode, 'store_code');

            if ($isEnabled && $process->getStatus() == $helper::STATUS_DISABLED) {
                // Set waiting status
                $process->modeWaiting();
                // Remove tmp xml
                $this->_removeTmpXml($storeCode);

                Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::STATUS, $helper->__('Schedule has been enabled'));
            } else if (!$isEnabled && $process->getStatus() != $helper::STATUS_DISABLED) {
                // Remove last scheduled task
                $this->_removeLastSchedule($process);

                // Disable process
                $process->modeDisabled($storeCode);

                // Remove tmp xml
                $this->_removeTmpXml($storeCode);

                Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::STATUS, $helper->__('Schedule has been disabled'));
            }

            if ($store->getIsActive()) {
                if ($resetSchedule && $isEnabled) {
                    $timecreated   = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
                    $timescheduled = $helper->getScheduledAt($config['time'], $config['frequency']);
                    $jobCode = $helper::JOB_CODE;

                    try {
                        $this->_rescheduleProcess($config, $process);
                    } catch (Exception $e) {
                        Mage::getSingleton('core/session')->addError('Error: '.$e);
                    }

                    Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::STATUS, $helper->__('Schedule has been reset'));
                }
            }
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

        $helper = Mage::helper('doofinder_feed');

        foreach ($stores as $store) {
            if ($store->getIsActive()) {

                $store_code = $store->getCode();
                $config = $helper->getStoreConfig($store_code);

                // Do not process the schedule if it has insufficient file permissions
                if (!$this->_checkFeedFilePermission($storeCode)) continue;

                // Always register process if not exists
                if (!$this->_isProcessRegistered($store_code)) {
                    $this->_registerProcess($store_code);
                }

                // Skip rest if feed is disabled
                if (!$config['enabled']) continue;

                $process = Mage::getModel('doofinder_feed/cron')->load($store_code, 'store_code');

                try {
                    // Check if process is running

                    $status = $process->getStatus();
                    $skipStatus = array(
                        $helper::STATUS_PENDING,
                        $helper::STATUS_RUNNING,
                        $helper::STATUS_DISABLED,
                    );

                    // If pending entry for store not exists add new
                    if (!(in_array($status, $skipStatus))) {
                        $this->_rescheduleProcess($config, $process);
                    }
                    // Otherwise check if the process still has a pending schedule
                    // if not recreate the schedule
                    else if (!$this->_processHasPendingSchedule($process)) {
                        Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::WARNING, $helper->__('Schedule has been missed'));
                        $process->setMessage($helper->__('Last schedule has been missed.'));
                        $helper->createNewSchedule($process);
                    }
                } catch (Exception $e) {
                    throw new Exception(Mage::helper('cron')->__('Unable to save Cron expression'));
                }
            }
        }
    }

    /**
     * Checks if process is registered in doofinder cron table
     *
     * @param string $store_code
     * @return bool
     */
    private function _isProcessRegistered($store_code = 'default')
    {
        $process = Mage::getModel('doofinder_feed/cron')->load($store_code, 'store_code');
        $data = $process->getData();
        if (empty($data)) {
            return false;
        }
        return true;
    }

    private function _registerProcess($store_code = 'default', $status = null)
    {
        $model = Mage::getModel('doofinder_feed/cron');
        $helper = Mage::helper('doofinder_feed');
        $config = $helper->getStoreConfig($store_code);
        if (empty($status)) {
            $status = $config['enabled'] ? $helper::STATUS_WAITING : $helper::STATUS_DISABLED;
        }

        $data = array(
            'store_code'    =>  $store_code,
            'status'        =>  $status,
            'message'       =>  $helper::MSG_EMPTY,
            'complete'      =>  '-',
            'next_run'      =>  '-',
            'next_iteration'=>  '-',
            'last_feed_name'=>  'None',
        );
        $model->setData($data)->save();
    }

    /**
     * Remove tmp xml file.
     *
     * @param string $store_code
     * @return bool
     */
    private function _removeTmpXml($store_code = null)
    {
        if (empty($store_code)) {
            return false;
        }
        $helper = Mage::helper('doofinder_feed');
        $config = $helper->getStoreConfig($store_code);
        $filePath = Mage::getBaseDir('media').DS.'doofinder'.DS.$config['xmlName'].'.tmp';
        if (file_exists($filePath)) {
            $success = unlink($filePath);
            if ($success) {
                Mage::getSingleton('core/session')->addSuccess("Temporary xml file: {$filePath} has beed removed.");
                return true;
            } else {
                Mage::getSingleton('core/session')->addError("Could not remove {$filePath}; This can lead to some errors. Remove this file manually.");
                return false;
            }
        }

        return false;
    }

    /**
     * Remove last scheduled entry in cron_schedule table.
     *
     * @param Doofinder_Feed_Model_Cron $process
     * @return bool
     */
    private function _removeLastSchedule(Doofinder_Feed_Model_Cron $process)
    {
        $lastSchedule = Mage::getModel('cron/schedule')->load($process->getScheduleId());

        if ($lastSchedule->getId()) {
            $lastSchedule->delete();
        }
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

        return is_writeable($dir) && (!file_exists($path) || is_writeable($path)) && (!file_exists($tmpPath) || is_writeable($tmpPath));
    }

    /**
     * Check if the given process has a pending schedule
     *
     * @param Doofinder_Feed_Model_Cron $process
     *
     * @return boolean
     */
    protected function _processHasPendingSchedule(Doofinder_Feed_Model_Cron $process)
    {
        $schedule = Mage::getModel('cron/schedule')->load($process->getScheduleId());
        return $schedule->getId() && $schedule->getStatus() == Mage_Cron_Model_Schedule::STATUS_PENDING;
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

        $timecreated   = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
        $timescheduled = $helper->getScheduledAt($config['time'], $config['frequency']);
        $jobCode = $helper::JOB_CODE;

        $schedule = Mage::getModel('cron/schedule');
        $schedule->setJobCode($jobCode)
            ->setCreatedAt($timecreated)
            ->setScheduledAt($timescheduled)
            ->setStoreCode($process->getStoreCode())
            ->save();

        $id = $schedule->getId();

        // Delete last scheduled entry if exists
        $this->_removeLastSchedule($process);

        $process->setStatus($helper::STATUS_PENDING)
            ->setComplete('0%')
            ->setNextRun($timescheduled)
            ->setNextIteration($timescheduled)
            ->setOffset(0)
            ->setScheduleId($id)
            ->setMessage($helper::MSG_PENDING)
            ->setErrorStack(0)
            ->save();

        Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::STATUS, $helper->__('Schedule has been regenerated'));
    }
}
