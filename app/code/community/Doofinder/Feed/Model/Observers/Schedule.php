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
                $lastId = $process->getScheduleId();
                $this->_removeLastSchedule($lastId);

                // Disable process
                $process->modeDisabled($storeCode);

                // Remove tmp xml
                $this->_removeTmpXml($storeCode);
                // Clear cron table
                $scheduleCollection = Mage::getModel('cron/schedule')
                    ->getCollection()
                    ->addFieldToFilter('schedule_id', $process->getScheduleId())
                    ->addFieldToFilter('job_code', $helper::JOB_CODE)
                    ->load();

                $this->clearScheduleTable($scheduleCollection);

                Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::STATUS, $helper->__('Schedule has been disabled'));
            }

            if ($store->getIsActive()) {
                if ($resetSchedule && $isEnabled) {
                    $timecreated   = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
                    $timescheduled = $helper->getScheduledAt($config['time'], $config['frequency']);
                    $jobCode = $helper::JOB_CODE;

                    try {
                        $scheduleCollection = Mage::getModel('cron/schedule')
                            ->getCollection()
                            ->addFieldToFilter('schedule_id', $process->getScheduleId())
                            ->addFieldToFilter('job_code', $helper::JOB_CODE)
                            ->load();

                        $this->clearScheduleTable($scheduleCollection);

                        $schedule = Mage::getModel('cron/schedule');
                        $schedule->setJobCode($jobCode)
                            ->setCreatedAt($timecreated)
                            ->setScheduledAt($timescheduled)
                            ->setStatus($helper::STATUS_PENDING)
                            ->setWebsiteId(intval($store->getWebsiteId()))
                            ->save();

                        $id = $schedule->getId();

                        $processTimescheduled = $helper->getScheduledAt($config['time'], $config['frequency']);
                        $process = Mage::getModel('doofinder_feed/cron')->load($storeCode, 'store_code');
                        $process->setStatus($helper::STATUS_PENDING)
                            ->setOffset(0)
                            ->setScheduleId($id)
                            ->setComplete('0%')
                            ->setNextRun($processTimescheduled)
                            ->setNextIteration($processTimescheduled)
                            ->setMessage($helper::MSG_PENDING)
                            ->setErrorStack(0)
                            ->save();

                        // Remove tmp xml
                        $this->_removeTmpXml($storeCode);
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

                $timecreated   = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
                $timescheduled = $helper->getScheduledAt($config['time'], $config['frequency']);
                $jobCode = $helper::JOB_CODE;


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
                        $schedule = Mage::getModel('cron/schedule');
                        $schedule->setJobCode($jobCode)
                            ->setCreatedAt($timecreated)
                            ->setScheduledAt($timescheduled)
                            ->setStoreCode($store->getCode())
                            ->save();

                        $id = $schedule->getId();

                        // Delete last scheduled entry if exists
                        $lastId = $process->getScheduleId();

                        $this->_removeLastSchedule($lastId);

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
                } catch (Exception $e) {
                    throw new Exception(Mage::helper('cron')->__('Unable to save Cron expression'));
                }
            }
        }
    }

    /**
     * Clears shedule table.
     *
     * @param Mage_Cron_Model_Resource_Schedule_Collection $scheduleCollection
     * @param array $status
     */
    public function clearScheduleTable(Mage_Cron_Model_Resource_Schedule_Collection $scheduleCollection, $status = array())
    {
        $helper = Mage::helper('doofinder_feed');
        foreach ($scheduleCollection as $job) {
            if ($job->getJobCode() === $helper::JOB_CODE && !in_array($job->getStatus(), $status) ) {
                Mage::getModel('cron/schedule')->load($job->getScheduleId())->delete();
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
     * @param int $lastId
     * @return bool
     */
    private function _removeLastSchedule($lastId = null)
    {
        if (empty($lastId)) {
            return false;
        }

        $lastSchedule = Mage::getModel('cron/schedule')->load($lastId);
        if ($lastSchedule->getData()) {
            $lastSchedule->delete();
            return true;
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
}
