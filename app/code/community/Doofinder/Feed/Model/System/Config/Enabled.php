<?php
class Doofinder_Feed_Model_System_Config_Enabled extends Mage_Core_Model_Config_Data
{
    protected function _afterSave()
    {
        $storeCode = Mage::app()->getRequest()->getParam('store');
        if ($storeCode) {

            $schedule = Mage::getModel('doofinder_feed/observers_schedule');

            $processModel = Mage::getModel('doofinder_feed/cron')->load($storeCode, 'store_code');


            if (empty($processModel->getData())) {
                return false;
            }
            $helper = Mage::helper('doofinder_feed');

            $enabled = $this->getValue();
            if ($enabled) {
                if (Mage::getStoreConfig('doofinder_cron/settings/enabled', $storeCode) == "0") {
                    $processModel->setStatus($helper::STATUS_WAITING)->save();
                }
            } else {
                // Reset process data
                $processModel->resetData($storeCode);

                // Clear cron table
                $scheduleCollection = Mage::getModel('cron/schedule')
                    ->getCollection()
                    ->addFieldToFilter('job_code', $helper::JOB_CODE)
                    ->addFieldToFilter('store_code', $storeCode)
                    ->load();

                $excludedStatuses = array(
                    $helper::STATUS_MISSED,
                    $helper::STATUS_PENDING,
                    $helper::STATUS_RUNNING,
                    $helper::STATUS_SUCCESS,
                    $helper::STATUS_WAITING,
                );
                $schedule->clearScheduleTable($scheduleCollection, $excludedStatuses);

            }

        }

    }
}
