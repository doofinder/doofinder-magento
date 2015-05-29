<?php

class Doofinder_Feed_Model_Observers_Schedule {

    const STATUS_PENDING = Mage_Cron_Model_Schedule::STATUS_PENDING;
    const STATUS_RUNNING = Mage_Cron_Model_Schedule::STATUS_RUNNING;
    const STATUS_SUCCESS = Mage_Cron_Model_Schedule::STATUS_SUCCESS;
    const JOB_CODE = 'doofinder_feed_generate';

    public function scheduleFeeds() {



        $stores = Mage::app()->getStores();

        $helper = Mage::helper('doofinder_feed');

        $scheduleCollection = Mage::getModel('cron/schedule')
            ->getCollection()
            ->load();

        $schedule = Mage::getModel('cron/schedule');

        $this->clearScheduleTable($scheduleCollection);

        foreach ($stores as $store) {
            $config = $helper->getStoreConfig($store->getCode());
            var_dump($config);
            var_dump($store->getData());
            $timecreated   = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
            $timescheduled = $helper->getScheduledAt($config['time'], $config['frequency']);
            $jobCode = self::JOB_CODE;
            try {
                $schedule->setJobCode($jobCode)
                    ->setCreatedAt($timecreated)
                    ->setScheduledAt($timescheduled)
                    ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
                    ->save();
            } catch (Exception $e) {
                     throw new Exception(Mage::helper('cron')->__('Unable to save Cron expression'));
            }
        }

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
