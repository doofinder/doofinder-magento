<?php
class Doofinder_Feed_Block_Settings_Status extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Error prefix
     * @var stirng
     */
    const ERROR_PREFIX = "#error#";

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $status = Mage::getModel('doofinder_feed/cron')->load('status')->getValue();
        $class = 'feed-message ';

        // Set default message
        if (!$status) {
            $status = 'Pending...';
        }

        // Mark message as an error
        if (strpos($status, self::ERROR_PREFIX) !== false) {
            $status = str_replace(self::ERROR_PREFIX, '', $status);
            $class .= 'error';
        }

        $html = "<p class='{$class}'>{$status}</p>";
        return $html;
    }
}
