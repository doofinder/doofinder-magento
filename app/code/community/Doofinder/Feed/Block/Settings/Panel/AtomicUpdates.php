<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.22
 */

class Doofinder_Feed_Block_Settings_Panel_AtomicUpdates extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $helper = Mage::helper('doofinder_feed');

        $this->setElement($element);
        $name = $element->getName();
        $element->setScopeLabel('');

        $messages = array();
        foreach (Mage::app()->getStores() as $store) {
            if ($store->getIsActive()) {
                $engineEnabled = Mage::getStoreConfig(
                    'doofinder_search/internal_settings/enable',
                    $store->getCode()
                );
                $atomicUpdatesEnabled = Mage::getStoreConfig(
                    'doofinder_cron/feed_settings/atomic_updates_enabled',
                    $store->getCode()
                );

                if (!$engineEnabled || !$atomicUpdatesEnabled) {
                    $message = $helper->__('Atomic updates are <strong>disabled</strong>.');
                } else {
                    $message = $helper->__(
                        'Atomic updates are <strong>enabled</strong>. ' .
                        'Your products will be automatically indexed when ' .
                        'they are created, updated or deleted.'
                    );
                }

                $messages[$store->getName()] = $message;
            }
        }

        if (count(array_unique($messages)) == 1) {
            return reset($messages);
        }

        $html = '<ul>';
        foreach ($messages as $name => $message) {
            $html .= '<li><strong>' . $name . ':</strong><p>' . $message . '</p></li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
