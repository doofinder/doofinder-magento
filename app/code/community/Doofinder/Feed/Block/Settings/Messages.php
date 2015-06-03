<?php
class Doofinder_Feed_Block_Settings_Messages extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Error prefix
     * @var stirng
     */
    const ERROR_PREFIX = "#error#";

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $msg = Mage::getModel('doofinder_feed/cron')->load('message')->getValue();
        $class = 'feed-message ';

        // Set default message
        if (!$msg) {
            $msg = 'Currently there are no messages.';
        }

        // Mark message as an error
        if (strpos($msg, self::ERROR_PREFIX) !== false) {
            $msg = str_replace(self::ERROR_PREFIX, '', $msg);
            $class .= 'error';
        }

        $html = "<p class='{$class}'>{$msg}</p>";
        return $html;
    }
}
