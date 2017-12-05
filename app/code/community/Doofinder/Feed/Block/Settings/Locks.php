<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.20
 */

class Doofinder_Feed_Block_Settings_Locks extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $element->setCanUseDefaultValue(false);
        return parent::render($element);
    }

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $helper = Mage::helper('doofinder_feed');

        $this->setElement($element);
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

        $locks = array();

        foreach ($stores as $store) {
            if ($helper->fileExists($helper->getFeedLockPath($store->getCode()))) {
                $msg = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('remove-lock')
                    ->setLabel($helper->__('Remove lock'))
                    ->setOnClick(
                        'confirm(\'Removing lock file may cause inconsistencies in generated feed. Proceed?\') ' .
                        ' && removeFeedLock(\'' . $store->getCode() . '\')'
                    )
                    ->toHtml();
            } else {
                $msg = $helper->__('Lock file for store <em>%s</em> does not exist.', $store->getCode());
            }

            $locks[$store->getCode()] = $msg;
        }

        $html = '';

        if (count($locks) > 1) {
            $html .= '<ul>';
            foreach ($locks as $code => $file) {
                $html .= '<li><b>' . $stores[$code]->getName() . ':</b><div>' . $file . '</div></li>';
            }

            $html .= '</ul>';
        } else {
            $html .= reset($locks);
        }

        $url = Mage::helper("adminhtml")->getUrl('adminhtml/doofinderFeedFeed/removeLock');
        $script = "<script type=\"text/javascript\">
            function removeFeedLock(storeCode) {
                var call = new Ajax.Request('" . $url . "', {
                    method: 'post',
                    parameters: {
                        store: storeCode
                    },
                    onComplete: function(transport) {
                        alert(transport.responseText);
                        window.location.reload();
                    }
                });
            }
        </script>";

        return $html . $script;
    }
}
