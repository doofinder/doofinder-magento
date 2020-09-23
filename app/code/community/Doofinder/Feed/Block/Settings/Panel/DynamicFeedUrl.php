<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.33
 */

class Doofinder_Feed_Block_Settings_Panel_DynamicFeedUrl extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $helper = Mage::helper('doofinder_feed');

        $this->setElement($element);
        $element->setScopeLabel('');

        $html = '<ul>';
        foreach (Mage::app()->getStores() as $store) {
            if ($store->getIsActive()) {
                $password = $store->getConfig('doofinder_cron/feed_settings/password');
                $params = array('language' => $store->getCode());

                if ($password) {
                    $params['password'] = $password;
                }

                $url = Mage::getUrl('doofinder/feed', array('_store' => $store->getCode(), '_nosid' => true) + $params);
                $anchor = '<a href="' . $url . '">' . $url . '</a>';
                $html .= '<li><strong>' . $store->getName() . ':</strong><p>' . $anchor . '</p></li>';
            }
        }

        $html .= '</ul>';
        $html .= '<p>';
        $html .= $helper->__(
            'If cron feed doesn\'t work for you, use these URLs to ' .
            'dynamically index your content from Doofinder. ' .
            'Contact support if you need help.'
        );
        $html .= '</p>';

        return $html;
    }
}
