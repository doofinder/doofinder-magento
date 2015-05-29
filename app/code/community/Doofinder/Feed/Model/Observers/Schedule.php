<?php

class Doofinder_Feed_Model_Observers_Schedule {

    const STATUS_PENDING = Mage_Cron_Model_Schedule::STATUS_PENDING;
    const STATUS_RUNNING = Mage_Cron_Model_Schedule::STATUS_RUNNING;
    const STATUS_SUCCESS = Mage_Cron_Model_Schedule::STATUS_SUCCESS;
    const JOB_CODE = 'doofinder_feed_generate';

    public function scheduleFeeds() {
        $stores = Mage::app()->getStores();

        $scheduleCollection = Mage::getModel('cron/schedule')
            ->getCollection()
            ->load();

        $schedule = Mage::getModel('cron/schedule');

        $this->clearScheduleTable($scheduleCollection);

        foreach ($stores as $store) {
            var_dump($store->getData());
        }

        die;

    }

    public function scheduleNewFeed($storeCode = null) {

    }


    protected function clearScheduleTable(Mage_Cron_Model_Resource_Schedule_Collection $scheduleCollection) {
        $status = array(
            self::STATUS_SUCCESS,
            self::STATUS_PENDING,
        );
        foreach ($scheduleCollection as $job) {
            if ($job->getJobCode() === self::JOB_CODE && in_array($job->getStatus(), $status) ) {
                Mage::getModel('cron/schedule')->load($job->getScheduleId())->delete();
            }
        }
    }

}
