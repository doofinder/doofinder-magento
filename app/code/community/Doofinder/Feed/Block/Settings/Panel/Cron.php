<?php
class Doofinder_Feed_Block_Settings_Panel_Cron extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    // 12 Hours in seconds
    const ALLOWED_TIME = 43200;

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $lastSchedule = Mage::getModel('cron/schedule')->getCollection()
            ->setOrder('finished_at', 'desc')
            ->getFirstItem();

        $message = '';
        if ($lastSchedule && count($lastSchedule->getData()) > 0) {
            $scheduleTime = strtotime($lastSchedule->getFinishedAt());
            $currentTime = time();

            // Difference in seconds
            $dif = ($currentTime - $scheduleTime);

            // If difference is bigger than allowed, display message
            if ($dif > self::ALLOWED_TIME) {

                $message = sprintf('Cron was run for the last time at %s. Taking into account the settings of the step delay option, there might be problems with the cron\'s configuration.', $lastSchedule->getFinishedAt());
                Mage::helper('doofinder_feed')->__($message);
            }
        } else {
            $message = Mage::helper('doofinder_feed')->__('There are no registered cron tasks. Please, check your system\'s crontab configuration.');
        }

        return '<p class="error">' . $message . '</p>';
    }

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = '<td class="label"></td>' .
            '<td class="value" colspan="3">' . $this->_getElementHtml($element) . '</td>';
        return $this->_decorateRowHtml($element, $html);
    }

    /**
     * Decorate field row html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @param string $html
     * @return string
     */
    protected function _decorateRowHtml($element, $html)
    {
        return '<tr id="row_' . $element->getHtmlId() . '">' . $html . '</tr>';
    }
}
