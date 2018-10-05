<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.27
 */

/**
 * Abstract Product Map Model for Doofinder Feed
 *
 * @version    1.8.27
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Model_Map_Product_Abstract extends Varien_Object
{
    protected $_fieldMap = null;
    protected $_skip = false;
    protected $_attributeSetModel;

    /**
     * @var Doofinder_Feed_Helper_Log
     */
    protected $_log;

    /**
     * Initialize log
     */
    public function _construct()
    {
        parent::_construct();
        $this->_log = Mage::helper('doofinder_feed/log');
    }

    public function initialize()
    {
        $this->_log->debugEnabled && $this->_log->debug(
            sprintf('Initializing %s for product %d', get_called_class(), $this->getProduct()->getId())
        );

        $currencyCode = Mage::app()
            ->getStore($this->getData('store_code'))
            ->getCurrentCurrencyCode();

        $imagesUrlPrefix = Mage::app()
            ->getStore($this->getData('store_id'))
            ->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA, false);
        $imagesUrlPrefix .= 'catalog/product';

        $imagesPathPrefix = Mage::getSingleton('catalog/product_media_config')
            ->getBaseMediaPath();

        $this->setData('store_currency_code', $currencyCode);
        $this->setData('images_url_prefix', $imagesUrlPrefix);
        $this->setData('images_path_prefix', $imagesPathPrefix);

        $this->_attributeSetModel = Mage::getModel('eav/entity_attribute_set');

        return $this;
    }

    public function map()
    {
        $this->_log->debugEnabled && $this->_log->debug(sprintf('Mapping product %d', $this->getProduct()->getId()));

        $this->_beforeMap();
        $rows = $this->_map();
        $this->_afterMap($rows);

        $this->_log->debugEnabled && $this->_log->debug(
            sprintf('Map for product %d: %s', $this->getProduct()->getId(), json_encode($rows))
        );

        return $rows;
    }

    public function _beforeMap()
    {
        return $this;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function _afterMap($rows)
    {
        return $this;
    }

    /**
     * @return array('column' => 'value')
     */
    protected function _map()
    {
        $fields = array();

        foreach (array_keys($this->_fieldMap) as $column) {
            $fields[$column] = $this->mapField($column);
        }

        $this->_attributeSetModel->load(
            $this->getProduct()->getAttributeSetId()
        );

        $categories = $this->getGenerator()->getCategories($this->getProduct());
        $fields['categories'] = implode(
            Doofinder_Feed_Model_Generator::CATEGORY_SEPARATOR,
            $categories
        );

        return array($fields);
    }

    protected function mapField($column)
    {
        $value = "";

        if (!isset($this->_fieldMap[$column]))
            return $value;

        $args = array('map' => $this->_fieldMap[$column]);
        $method = 'mapField' . $this->_camelize($column);

        if (method_exists($this, $method))
            $value = $this->$method($args);
        else
            $value = $this->getFieldValue($args);

        return $value;
    }

    protected function mapAttribute($params = array())
    {
        $map = $params['map'];
        $product = $this->getProduct();
        $fieldData = '';

        $attribute = $this->getGenerator()->getAttribute($map['attribute']);
        if ($attribute === false)
            $this->_attributeDoesNotExist($map['attribute']);

        $fieldData = $this->getAttributeValue($product, $attribute);

        return $this->cleanField($fieldData);
    }

    protected function mapAttributeDescription($params = array())
    {
        $map = $params['map'];
        $product = $this->getProduct();

        $attribute = $this->getGenerator()
            ->getAttribute($map['attribute']);

        if ($attribute === false)
            $this->_attributeDoesNotExist($map['attribute']);

        $description = $this->getAttributeValue($product, $attribute);

        return $this->cleanField($description);
    }

    protected function mapDirectiveId()
    {
        $fieldData = $this->getProduct()->getId();
        return $this->cleanField($fieldData);
    }

    protected function mapDirectiveUrl()
    {
        $product = $this->getProduct();
        return $product->getUrlModel()->getUrl($product, array('_nosid' => true));
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    // @codingStandardsIgnoreStart
    protected function mapDirectiveImageLink($args, $attributeName = 'image')
    {
    // @codingStandardsIgnoreEnd
        $product = $this->getProduct();
        $image = $product->getData($attributeName);

        if ($image != 'no_selection' && $image != "") {
            $image = Mage::helper('catalog/image')
                ->init($product, $attributeName);

            if ($size = $this->getGenerator()->getData('image_size')) {
                $image->resize($size);
            }

            return (string) $image;
        }

        return "";
    }

    protected function mapDirectiveImageLinkThumbnail($args)
    {
        return $this->mapDirectiveImageLink($args, 'thumbnail');
    }

    protected function mapDirectiveImageLinkSmall($args)
    {
        return $this->mapDirectiveImageLink($args, 'small_image');
    }

    /**
     * Get product price
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $field
     * @return string|null
     */
    protected function getProductPrice($field)
    {
        if (!Mage::getStoreConfig('doofinder_cron/feed_settings/display_price', $this->getStoreCode())) {
            return null;
        }

        $tax = null;
        if (Mage::helper('tax')->needPriceConversion($this->getStoreCode())) {
            switch (Mage::getStoreConfig('doofinder_cron/feed_settings/price_tax_mode', $this->getStoreCode())) {
                case Doofinder_Feed_Model_System_Config_Source_Feed_Pricetaxmode::MODE_WITH_TAX:
                    $tax = true;
                    break;

                case Doofinder_Feed_Model_System_Config_Source_Feed_Pricetaxmode::MODE_WITHOUT_TAX:
                    $tax = false;
                    break;
            }
        }

        $price = Mage::helper('doofinder_feed')->getProductPrice($this->getProduct(), $field, $tax);

        if ($price === null) {
            return $price;
        }

        $store = Mage::app()->getStore($this->getStoreCode());

        // Return price converted to store currency
        return Mage::helper('core')->currencyByStore($price, $store, false, false);
    }

    protected function mapFieldPrice()
    {
        return $this->mapDirectivePrice();
    }

    protected function mapDirectivePrice()
    {
        return $this->getProductPrice('price');
    }

    protected function mapDirectiveSalePrice()
    {
        return $this->getProductPrice('sale_price');
    }

    protected function mapDirectiveCurrency()
    {
        return $this->getData('store_currency_code');
    }

    protected function mapDirectiveAvailability($params = array())
    {
        $map = $params['map'];
        $product = $this->getProduct();

        $defaultVal = isset($map['default_value']) ? $map['default_value'] : "";

        if ($defaultVal != "") {
            $stockStatus = $defaultVal;
            $stockStatus = trim(strtolower($stockStatus));

            if (false === array_search($stockStatus, (array) $this->getConfig()->getAllowedStockStatuses()))
                $stockStatus = $this->getConfig()->getOutOfStockStatus();

            $fieldData = $stockStatus;
            $fieldData = $this->cleanField($fieldData);

            return $fieldData;
        }

        $fieldData = $this->getConfig()->getOutOfStockStatus();

        $stockItem = Mage::getModel('cataloginventory/stock_item');
        $stockItem->setStoreId($this->getStoreId());
        $stockItem->getResource()->loadByProductId($stockItem, $product->getId());
        $stockItem->setOrigData();

        if ($stockItem->getId() && $stockItem->getIsInStock())
            $fieldData = $this->getConfig()->getInStockStatus();

        return $fieldData;
    }

    protected function mapDirectiveCondition($params = array())
    {
        $map = $params['map'];

        $defaultVal = isset($map['default_value']) ? $map['default_value'] : "";
        $defaultVal = trim(strtolower($defaultVal));

        if (false === array_search($defaultVal, (array) $this->getConfig()->getAllowedConditions()))
            $defaultVal = $this->getConfig()->getConditionNew();

        $fieldData = $defaultVal;
        $fieldData = $this->cleanField($fieldData);

        return $fieldData;
    }


    //
    // Mapping::Fields
    //

    public function mapFieldProductType($params = array())
    {
        $args = array('map' => $params['map']);
        $value = "";

        $mapByCategory = $this->getConfig()->getMapCategorySorted(
            'product_type_by_category',
            $this->getStoreId()
        );

        $categoryIds = $this->getProduct()->getCategoryIds();

        if (!empty($categoryIds) && !empty($mapByCategory)) {
            foreach ($mapByCategory as $arr) {
                if (array_search($arr['category'], $categoryIds) !== false) {
                    $value = $arr['value'];
                    break;
                }
            }
        }

        if ($value != "")
            return htmlspecialchars_decode($value);

        $value = $this->getFieldValue($args);

        return htmlspecialchars_decode($value);
    }

    public function getFieldValue($args = array())
    {
        $value = "";
        $attName = $args['map']['attribute'];

        if ($this->getConfig()->isDirective($attName, $this->getStoreId())) {
            $attName = str_replace('df_directive_', '', $attName);
            $method = 'mapDirective' . $this->_camelize($attName);

            if (method_exists($this, $method))
                $value = $this->$method($args);
        } else {
            $method = 'mapAttribute' . $this->_camelize($attName);

            if (method_exists($this, $method))
                $value = $this->$method($args);
            else
                $value = $this->mapAttribute($args);
        }

        return $value;
    }

    public function getAttributeValue($product, $attribute)
    {
        $attrCode = $attribute->getAttributeCode();

        if ($attribute->getFrontendInput() == 'select'
            || $attribute->getFrontendInput() == 'multiselect'
        ) {
            if ($product->getResource()->getAttribute($attrCode) !== null)
                $value = $product->getAttributeText($attrCode);
        } else {
            $value = $product->getData($attrCode);
        }

        return $value;
    }

    public function loadAssocIds($product, $storeId)
    {
        $assocIds = array();

        if ($product->getTypeId() != Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
            return false;

        $children = $this->getTools()->getChildsIds($product->getId());
        if ($children === false)
            return $assocIds;

        $children = $this->getTools()->getProductInStoresIds($children);
        foreach ($children as $assocId => $s) {
            $attr = $this->getGenerator()->getAttribute('status');
            $status = $this->getTools()->getProductAttributeValueBySql(
                $attr,
                $attr->getBackendType(),
                $assocId,
                $storeId
            );

            if ($status != Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                continue;

            if (is_array($s) && array_search($storeId, $s) !== false)
                $assocIds[] = $assocId;
        }

        return $assocIds;
    }

    protected function cleanField($field)
    {
        if (is_array($field)) {
            foreach ($field as &$value) {
                $value = $this->cleanFieldValue($value);
                unset($value);
            }
        } else {
            $field = $this->cleanFieldValue($field);
        }

        return $field;
    }

    /**
     * Cleans invalid utf8 characters, strips tags and trims
     *
     * @param string|array $field
     */
    protected function cleanFieldValue($field)
    {
        // Do nothing if field is empty
        if (!$field) return $field;

        $cleaned = $this->cleanFieldValueArray((array) $field);
        return is_array($field) ? $cleaned : $cleaned[0];
    }

    protected function cleanFieldValueArray($fields)
    {
        return array_map(array($this, '_cleanFieldValue'), $fields);
    }

    protected function _cleanFieldValue($field)
    {
        // http://stackoverflow.com/questions/4224141/php-removing-invalid-utf-8-characters-in-xml-using-filter
        $validUtf = '/([\x09\x0A\x0D\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|' .
                      '\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|' .
                      '\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|' .
                      '[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})|./x';

        $field = preg_replace('#<br(\s?/)?>#i', ' ', $field);
        $field = strip_tags($field);
        $field = preg_replace('/[ ]{2,}/', ' ', $field);
        $field = trim($field);
        // Use a double slash to prevent the doofinder from treating it as a separator
        $field = str_replace('/', '//', $field);
        // @codingStandardsIgnoreStart
        $field = html_entity_decode($field, null, 'UTF-8');
        // @codingStandardsIgnoreEnd

        return preg_replace($validUtf, '$1', $field);
    }

    protected function _attributeDoesNotExist($attName)
    {
        Mage::throwException($attName . ' attribute does not exist!');
    }

    public function getConfig()
    {
        return $this->getGenerator()->getConfig();
    }

    public function getConfigVar($key, $section = Doofinder_Feed_Model_Config::DEFAULT_SECTION)
    {
        return $this->getGenerator()->getConfigVar($key, null, $section);
    }

    public function getTools()
    {
        return $this->getGenerator()->getTools();
    }

    public function isSkip()
    {
        return $this->_skip;
    }

    public function checkSkipSubmission()
    {
        // Check title and description
        if (!$this->_skip) {
            $title = $this->mapField('title');
            $description = $this->mapField('description');
            $this->_skip = !strlen($title) && !strlen($description);
        }

        return $this;
    }

    public function setFieldsMap($arr)
    {
        $this->_fieldMap = $arr;

        return $this;
    }
}
