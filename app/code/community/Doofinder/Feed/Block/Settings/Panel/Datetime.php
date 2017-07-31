<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.16
 */

class Doofinder_Feed_Block_Settings_Panel_Datetime extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $name = $element->getName();
        $element->setScopeLabel('');
        $code = Mage::app()->getRequest()->getParam('store');
        $field = $this->_getField($name);
        $html = '';
        if ($field && $code) {
            $datetime = Mage::getModel('doofinder_feed/cron')->load($code, 'store_code')->getData($field);
            if ($datetime) {
                $msg = $datetime;

                try {
                    // @codingStandardsIgnoreStart
                    $date = Mage::getSingleton('core/date')->date(null, $datetime);
                    // @codingStandardsIgnoreEnd
                    $msg = Mage::helper('core')->formatDate($date, null, true);
                } catch (Exception $e) {
                    Mage::logException($e);
                }

                $class = 'feed-datetime';
                $html = "<p class='{$class}'>{$msg}</p>";
            }
        }

        return $html;
    }

    protected function _getField($name = null)
    {
        $pattern = '/groups\[panel\]\[fields\]\[([a-z_-]*)\]\[value\]/';
        $preg = preg_match($pattern, $name, $match);
        if ($preg && isset($match[1])) {
            return $match[1];
        } else {
            return false;
        }
    }
}
