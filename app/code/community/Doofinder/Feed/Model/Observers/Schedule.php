<?php

class Doofinder_Feed_Model_Observers_Schedule {

    const STATUS_PENDING    = Mage_Cron_Model_Schedule::STATUS_PENDING;
    const STATUS_RUNNING    = Mage_Cron_Model_Schedule::STATUS_RUNNING;
    const STATUS_SUCCESS    = Mage_Cron_Model_Schedule::STATUS_SUCCESS;
    const STATUS_MISSED     = Mage_Cron_Model_Schedule::STATUS_MISSED;
    const STATUS_ERROR      = Mage_Cron_Model_Schedule::STATUS_ERROR;
    const JOB_CODE          = 'doofinder_feed_generate';

    public function saveNewSchedule($observer) {
        Mage::log('Saving new schedule: ' .date('c', time()));
        // Get store code
        $storeCode = $observer->getStore();

        // Do nothing if there is no store code
        if (!$storeCode) return;



        // Get store
        $store = Mage::app()->getStore($storeCode);
        $helper = Mage::helper('doofinder_feed');
        $config = $helper->getStoreConfig($storeCode);

        $resetSchedule = (bool)$config['reset'];
        $isEnabled = (bool)$config['enabled'];
        // Register process if not exists
        if (!$this->_isProcessRegistered($storeCode)) {
            $status = $resetSchedule ? $helper::STATUS_PENDING : $helper::STATUS_ENABLED;
            $this->_registerProcess($storeCode, $status);
        }

        $scheduleCollection = Mage::getModel('cron/schedule')
            ->getCollection()
            ->addFieldToFilter('job_code', self::JOB_CODE)
            ->addFieldToFilter('store_code', $storeCode)
            ->load();

        $excludedStatuses = array(
            self::STATUS_SUCCESS,
            self::STATUS_MISSED,
            self::STATUS_PENDING,
        );

        $this->_clearScheduleTable($scheduleCollection, $excludedStatuses);

        if ($store->getIsActive()) {


            if ($resetSchedule && $isEnabled) {
                Mage::log('Resetting schedule.');
                $timecreated   = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));

                $timescheduled = $helper->getScheduledAt($config['time'], $config['frequency']);
                $jobCode = self::JOB_CODE;

                try {
                    // Check if entry exists and is pending
                    // Temporarily disabled until process model implementation

                    /*$entry = Mage::getModel('cron/schedule')
                        ->getCollection()
                        ->addFieldToFilter('job_code', self::JOB_CODE)
                        ->addFieldToFilter('status', self::STATUS_PENDING)
                        ->addFieldToFilter('store_code', $store->getCode())
                        ->load();*/

                    // If pending entry for store not exists add new
                    #if (!$entry->count()) {
                        $schedule = Mage::getModel('cron/schedule');
                        $schedule->setJobCode($jobCode)
                            ->setCreatedAt($timecreated)
                            ->setScheduledAt($timescheduled)
                            ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
                            ->setWebsiteId(intval($store->getWebsiteId()))
                            ->setStoreCode($store->getCode())
                            ->save();

                    #}
                } catch (Exception $e) {
                         throw new Exception(Mage::helper('cron')->__('Unable to save Cron expression'));
                }
            }
        }

    }

    public function regenerateSchedule() {
        Mage::log('Regenerating Schedule: '.date('c', time()));
        // Get store
        $stores = Mage::app()->getStores();

        $helper = Mage::helper('doofinder_feed');

        foreach ($stores as $store) {
            if ($store->getIsActive()) {
                $config = $helper->getStoreConfig($store->getCode());

                // Skip if feed is disabled
                if (!$config['enabled']) continue;

                // Register process if not exists
                if (!$this->_isProcessRegistered($store->getCode())) {
                    $this->_registerProcess($store->getCode());
                }

                Mage::log('Resetting schedule for '.$store->getCode());
                $timecreated   = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
                $timescheduled = $helper->getScheduledAt($config['time'], $config['frequency']);
                $jobCode = self::JOB_CODE;

                try {
                    // Check if entry exists and is pending
                    $entry = Mage::getModel('cron/schedule')
                        ->getCollection()
                        ->addFieldToFilter('job_code', self::JOB_CODE)
                        ->addFieldToFilter('status', self::STATUS_PENDING)
                        ->addFieldToFilter('store_code', $store->getCode())
                        ->load();

                    // If pending entry for store not exists add new
                    if (!$entry->getSize()) {
                        $schedule = Mage::getModel('cron/schedule');
                        $schedule->setJobCode($jobCode)
                            ->setCreatedAt($timecreated)
                            ->setScheduledAt($timescheduled)
                            ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
                            ->setWebsiteId(intval($store->getWebsiteId()))
                            ->setStoreCode($store->getCode())
                            ->save();

                    }
                } catch (Exception $e) {
                         throw new Exception(Mage::helper('cron')->__('Unable to save Cron expression'));
                }
            }
        }
    }



    /**
     * Clears shedule table
     * @param Mage_Cron_Model_Resource_Schedule_Collection $scheduleCollection
     * @param array $status
     */
    private function _clearScheduleTable(Mage_Cron_Model_Resource_Schedule_Collection $scheduleCollection, $status = array()) {

        foreach ($scheduleCollection as $job) {
            if ($job->getJobCode() === self::JOB_CODE && in_array($job->getStatus(), $status) ) {
                Mage::getModel('cron/schedule')->load($job->getScheduleId())->delete();
            }
        }
    }

    /**
     * Checks if process is registered in doofinder cron table
     * @param string $store_code
     * @return bool
     */
    private function _isProcessRegistered($store_code = 'default') {
        $process = Mage::getModel('doofinder_feed/cron')->load($store_code, 'store_code');
        if (empty($process->getData())) {
            return false;
        }
        return true;
    }

    private function _registerProcess($store_code = 'default', $status = null) {
        $model = Mage::getModel('doofinder_feed/cron');
        $helper = Mage::helper('doofinder_feed');
        $config = $helper->getStoreConfig($store_code);
        if (empty($status)) {
            $status = $config['enabled'] ? $helper::STATUS_ENABLED : $helper::STATUS_DISABLED;
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

}
