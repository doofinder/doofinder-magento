<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Model_System_Config_Source_Product_Attributes
{
    protected $_options;

    public function toOptionArray()
    {
        if (!$this->_options) {
            $attributes = array();

            $result = Mage::getResourceModel('catalog/product_attribute_collection')->load();

            foreach ($result as $attribute) {
                $code = $attribute->getAttributeCode();
                $label = $attribute->getFrontendLabel();
                $attributes[$code] = 'Attribute: ' . $code . ($label ? ' (' . $label . ')' : '');
            }

            $this->_options = array_merge(
                $this->_getDoofinderDirectivesOptionArray(),
                $attributes
            );
        }

        return $this->_options;
    }

    protected function _getDoofinderDirectivesOptionArray()
    {
        $options = array();

        foreach (Mage::getSingleton('doofinder_feed/config')->getConfigVar('directives') as $directive => $info) {
            $options[$directive] = 'Doofinder: ' . $info['label'];
        }

        return $options;
    }
}
