<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

/**
 * Config model for Doofinder Feed
 *
 * @version    1.8.7
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Model_Config extends Mage_Core_Model_Config_Data
{
    const DEFAULT_SECTION  = 'feed';
    const BASE_CONFIG_PATH = 'doofinder';

    protected $_directives = null;
    protected $_product_attribute_codes = null;
    protected $_product_directives = null;

    const OUT_OF_STOCK = 'out of stock';
    const IN_STOCK = 'in stock';

    public function getOutOfStockStatus() {
        return self::OUT_OF_STOCK;
    }

    public function getInStockStatus() {
        return self::IN_STOCK;
    }

    //
    // Get Config
    //

    public function getConfigVar($key, $storeId = null,
                                 $section = self::DEFAULT_SECTION)
    {
         if ($key == 'field_map')
            return $this->getConfigVarFieldMap($key, $storeId, $section);

        $path = self::BASE_CONFIG_PATH . '/' . $section . '/' . $key;

        return Mage::getStoreConfig($path, $storeId);
    }

    public function getConfigVarFieldMap($key, $storeId = null,
                                         $section = self::DEFAULT_SECTION)
    {
        $path = self::BASE_CONFIG_PATH . '/' . $section . '/' . $key;
        $data = Mage::getStoreConfig($path, $storeId);

        if (empty($data))
            $data = $this->convertDefaultFieldMap($storeId);

        return $data;
    }

    public function getMultipleSelectVar($key, $storeId = null,
                                         $section = self::DEFAULT_SECTION)
    {
        $str = $this->getConfigVar($key, $storeId, $section);
        $values = array();

        if (!empty($str))
        {
            $values = explode(',', $str);
        }

        return array_filter($values);
    }


    //
    // Defaults
    //

    public function convertDefaultFieldMap($storeId = null)
    {
        $result = array();
        $defaultMapping = $this->getConfigVar('default_field_map', $storeId);

        foreach ($defaultMapping as $field => $config)
        {
            $result[] = array(
                'label' => $config['label'],
                'attribute' => $config['attribute'],
                'field' => $field,
            );
        }

        return $result;
    }


    //
    // Checks
    //

    public function isDirective($code, $storeId = null)
    {
        if (is_null($this->_directives))
            $this->_directives = $this->getConfigVar('directives', $storeId);

        return isset($this->_directives[$code]);
    }

    /**
     * Returns 1 if the current version is greater than the specified in the
     * parameter. 0 if is equal. -1 if is lower.
     */
    public function compareMagentoVersion($infoArray)
    {
        $v = Mage::getVersionInfo();

        foreach (array('major', 'minor', 'revision', 'patch') as $key)
        {
            if ($v[$key] != $infoArray[$key])
                return $v[$key] > $infoArray[$key] ? 1 : -1;
        }

        return 0;
    }


    //
    // Tools for Dropdowns
    //

    // protected function _loadProductAttributeCodes($storeId = null)
    // {
    //     if (!is_null($this->_product_attribute_codes))
    //         return;

    //     $config = Mage::getModel('eav/config');

    //     $this->_product_attribute_codes = array();

    //     $excludedAttrs = $this->getMultipleSelectVar('excluded_attributes');
    //     $attributesCodes = $config->getEntityAttributeCodes(
    //         'catalog_product',
    //         new Varien_Object(array('store_id' => $storeId))
    //     );

    //     foreach ($attributesCodes as $attrCode)
    //     {
    //         if (array_search($attrCode, $excludedAttrs) !== false)
    //             continue;

    //         $attr = $config->getAttribute('catalog_product', $attrCode);

    //         if ($attr !== false && $attr->getAttributeId() > 0)
    //         {
    //             $code = $attr->getAttributeCode();
    //             $this->_product_attribute_codes[$code] = addslashes(
    //                 $attr->getFrontend()->getLabel().' ('.$code.')'
    //             );
    //         }
    //     }

    //     asort($this->_product_attribute_codes);
    // }

    protected function _loadProductDirectives($storeId = null)
    {
        if (!is_null($this->_product_directives))
            return;

        $this->_product_directives = array();

        foreach ($this->getConfigVar('directives', $storeId) as $code => $cfg)
        {
            $this->_product_directives[$code] = $cfg['label'];
        }

        asort($this->_product_directives);
    }

    public function getProductAttributesCodes($storeId = null,
                                              $includeDirectives = true)
    {
        $this->_loadProductAttributeCodes($storeId);

        if ($includeDirectives === true)
        {
            $this->_loadProductDirectives($storeId);

            return array_merge($this->_product_directives,
                               $this->_product_attribute_codes);
        }

        return $this->_product_attribute_codes;
    }
}
