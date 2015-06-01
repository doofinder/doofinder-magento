<?php

class Doofinder_Feed_Model_Observers_Schedule {

    const STATUS_PENDING    = Mage_Cron_Model_Schedule::STATUS_PENDING;
    const STATUS_RUNNING    = Mage_Cron_Model_Schedule::STATUS_RUNNING;
    const STATUS_SUCCESS    = Mage_Cron_Model_Schedule::STATUS_SUCCESS;
    const STATUS_MISSED     = Mage_Cron_Model_Schedule::STATUS_MISSED;
    const STATUS_ERROR      = Mage_Cron_Model_Schedule::STATUS_ERROR;
    const JOB_CODE          = 'doofinder_feed_generate';

    public function saveNewSchedule() {
        $this->_scheduleFeeds(true);
    }

    public function regenerateSchedule() {
        Mage::log('Regenerating Schedule');
        $this->_scheduleFeeds();
    }

    private function _scheduleFeeds($clear = false) {

        $stores = Mage::app()->getStores();

        $helper = Mage::helper('doofinder_feed');

        $scheduleCollection = Mage::getModel('cron/schedule')
            ->getCollection()
            ->addFieldToFilter('job_code', self::JOB_CODE)
            ->load();
        $schedule = Mage::getModel('cron/schedule');

        if ($clear && $scheduleCollection) {
            $status = array(
                self::STATUS_SUCCESS,
                self::STATUS_MISSED,
                self::STATUS_PENDING,
            );

            $this->clearScheduleTable($scheduleCollection, $status);
        }

        foreach ($stores as $store) {
            if ($store->getIsActive()) {
                $config = $helper->getStoreConfig($store->getCode());
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
                    if (!$entry->count()) {
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


    protected function clearScheduleTable(Mage_Cron_Model_Resource_Schedule_Collection $scheduleCollection, $status = array()) {

        foreach ($scheduleCollection as $job) {
            if ($job->getJobCode() === self::JOB_CODE && in_array($job->getStatus(), $status) ) {
                Mage::getModel('cron/schedule')->load($job->getScheduleId())->delete();
            }
        }
    }

}
