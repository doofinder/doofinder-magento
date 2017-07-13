<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.13
 */

class Doofinder_Feed_Block_Settings_Panel_File extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Error prefix
     * @var string
     */
    const ERROR_PREFIX = "#error#";

    // 12 Hours in seconds
    const ALLOWED_TIME = 43200;

    protected function getCronMessage()
    {
        $lastSchedule = Mage::getModel('cron/schedule')->getCollection()
            ->setOrder('finished_at', 'desc')
            ->setPageSize(1)
            ->getItems();

        $lastSchedule = $lastSchedule ? reset($lastSchedule) : null;
        $lastScheduleData = $lastSchedule ? $lastSchedule->getData() : array();

        $message = '';
        if ($lastSchedule && !empty($lastScheduleData)) {
            $scheduleTime = strtotime($lastSchedule->getFinishedAt());
            $currentTime = Mage::getSingleton('core/date')->timestamp();

            // Difference in seconds
            $dif = ($currentTime - $scheduleTime);

            // If difference is bigger than allowed, display message
            if ($dif > self::ALLOWED_TIME) {
                $message = sprintf(
                    'Cron was run for the last time at %s. ' .
                    'Taking into account the settings of the step delay option, ' .
                    'there might be problems with the cron\'s configuration.',
                    $lastSchedule->getFinishedAt()
                );
                Mage::helper('doofinder_feed')->__($message);
            }
        } else {
            $message = Mage::helper('doofinder_feed')->__(
                'There are no registered cron tasks. ' .
                'Please, check your system\'s crontab configuration.'
            );
        }

        return '<p class="error">' . $message . '</p>';
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $helper = Mage::helper('doofinder_feed');

        $this->setElement($element);
        $name = $element->getName();
        $element->setScopeLabel('');
        $storeCode = Mage::app()->getRequest()->getParam('store');

        $stores = array();

        if ($storeCode) {
            $stores[$storeCode] = Mage::getModel('core/store')->load($storeCode);
        } else {
            foreach (Mage::app()->getStores() as $store) {
                if ($store->getIsActive()) {
                    $stores[$store->getCode()] = $store;
                }
            }
        }

        $enabled = false;
        $messages = array();

        foreach ($stores as $store) {
            if (!$store->getConfig('doofinder_cron/schedule_settings/enabled')) {
                $message = $helper->__('Cron-based feed generation is <strong>disabled</strong>.');
            } else {
                $enabled = true;
                // @codingStandardsIgnoreStart
                $process = Mage::getModel('doofinder_feed/cron')->load($store->getCode(), 'store_code');
                // @codingStandardsIgnoreEnd
                $lastGeneratedName = $process->getLastFeedName();

                $fileUrl = Mage::getBaseUrl('media').'doofinder'.DS.$lastGeneratedName;
                $fileDir = Mage::getBaseDir('media').DS.'doofinder'.DS.$lastGeneratedName;

                if ($lastGeneratedName && (new Varien_Io_File())->fileExists($fileDir)) {
                    $message = '<p><a href=' . $fileUrl . ' target="_blank">';
                    $message .= !empty($stores) ? $fileUrl : $helper->__('Get %s', $lastGeneratedName);
                    $message .= '</a></p>';
                } else {
                    $message = '<p>' . $helper->__('Currently there is no file to preview.') . '</p>';
                }

                $time = explode(',', Mage::getStoreConfig('doofinder_cron/schedule_settings/time', $store->getCode()));
                $message .= '<p>';
                $message .= $helper->__(
                    'Cron-based feed generation is <strong>enabled</strong>. ' .
                    'Feed generation is being scheduled at %s:%s.',
                    $time[0],
                    $time[1]
                );
                $message .= '</p>';
            }

            $messages[$store->getName()] = $message;
        }

        $html = '';

        if ($enabled) {
            $html .= $this->getCronMessage();
        }

        if (count(array_unique($messages)) == 1) {
            return $html . reset($messages);
        }

        $html .= '<ul>';
        foreach ($messages as $name => $message) {
            $html .= '<li><strong>' . $name . ':</strong><p>' . $message . '</p></li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
