<?php
class Doofinder_Feed_Model_Map_Product_Abstract extends Varien_Object
{
    protected $_field_map = null;
    protected $skip = false;
    protected $_attributeSetModel;


    public function initialize()
    {
        $currency_code = Mage::app()
            ->getStore($this->getData('store_code'))
            ->getCurrentCurrencyCode();

        $images_url_prefix = Mage::app()
            ->getStore($this->getData('store_id'))
            ->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA, false);
        $images_url_prefix .= 'catalog/product';

        $images_path_prefix = Mage::getSingleton('catalog/product_media_config')
            ->getBaseMediaPath();

        $this->setData('store_currency_code', $currency_code);
        $this->setData('images_url_prefix', $images_url_prefix);
        $this->setData('images_path_prefix', $images_path_prefix);

        $this->_attributeSetModel = Mage::getModel('eav/entity_attribute_set');

        return $this;
    }

    public function map()
    {
        $this->_beforeMap();
        $rows = $this->_map();
        $this->_afterMap($rows);

        return $rows;
    }

    public function _beforeMap()
    {
        return $this;
    }

    public function _afterMap($rows)
    {
        return $this;
    }


    //
    // protected::Mapping
    //

    /**
     * @return array('column' => 'value')
     */
    protected function _map()
    {
        $fields = array();

        foreach ($this->_field_map as $column => $arr)
            $fields[$column] = $this->mapField($column);

        // $fields['magento_store'] = $this->getData('store_code');

        $this->_attributeSetModel->load(
            $this->getProduct()->getAttributeSetId());
        // $fields['attribute_set'] = $this->_attributeSetModel
        //     ->getAttributeSetName();

        // BEGIN: Export all user attributes
        $product = $this->getProduct();

        $attrs = Mage::getResourceModel('eav/entity_attribute_collection')
            ->addFieldToFilter('is_user_defined', 1)
            ->setEntityTypeFilter($product->getResource()->getTypeId())
            ->setAttributeSetFilter($product->getAttributeSetId())
            ->load();

        foreach ($attrs as $attr)
        {
            $idx = strtolower(preg_replace('/[^0-9a-z_]+/UiS', '',
                                           $attr->attribute_code));

            if (preg_match('/^[0-9]/S', $idx))
                $idx = 'n'.$idx;

            $fields[$idx] = $this->mapDoofinderAttribute($attr);
        }
        // END

        $i = 0;
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

        if (!isset($this->_field_map[$column]))
            return $value;

        $args = array('map' => $this->_field_map[$column]);
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

    protected function mapDoofinderAttribute($attribute, $product = null)
    {
        if (is_null($product))
            $product = $this->getProduct();

        if ($attribute === false)
            $this->_attributeDoesNotExist($map['attribute']);

        $fieldData = $this->getAttributeValue($product, $attribute);

        return $this->cleanField($fieldData);
    }


    //
    // protected::Mapping::Attributes
    //

    protected function mapAttributeDescription($params = array())
    {
        $map = $params['map'];
        $product = $this->getProduct();
        $fieldData = "";

        $attribute = $this->getGenerator()
            ->getAttribute($map['attribute']);

        if ($attribute === false)
            $this->_attributeDoesNotExist($map['attribute']);

        $description = $this->getAttributeValue($product, $attribute);

        return $this->cleanField($description);
    }


    //
    // protected::Mapping::Directives
    //

    protected function mapDirectiveId($params = array())
    {
        // $storeCode = $this->getStoreCode();
        $fieldData = $this->getProduct()->getId();
        // $fieldData .= '_'.preg_replace('/[^a-zA-Z0-9]/', '', $storeCode);

        return $this->cleanField($fieldData);
    }

    protected function mapDirectiveUrl($params = array())
    {
        $product = $this->getProduct();

        if (count($product->getCategoryIds()) > 0)
            return sprintf(
                '%s%s',
                Mage::app()->getStore($this->getData('store_id'))
                    ->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK),
                $product->getUrlPath()
            );
        else
            return $product->getProductUrl();
    }

    protected function mapDirectiveImageLink($params = array())
    {
        $product = $this->getProduct();
        $image = $product->getData('image');

        if ($image != 'no_selection' && $image != "")
            return (string) Mage::helper('catalog/image')
                ->init($product, 'image')
                ->resize(120);
        else
            return "";
    }

    protected function mapDirectivePrice($params = array())
    {
        $storeId = $this->getStoreId();
        $product = $this->getProduct();
        $helper = Mage::helper('doofinder_feed/tax');

        $includingTax = true;

        $fieldData = $helper->getPrice(
            $product,
            $this->getPrice(),
            $includingTax,
            false,
            false,
            null,
            $storeId,
            ($helper->priceIncludesTax($storeId) ? true : false)
        );
        $fieldData = $this->cleanField($fieldData);

        if ($fieldData < 0)
            $this->skip = true;

        return $fieldData;
    }

    protected function mapDirectiveSalePrice($params = array())
    {
        $product = $this->getProduct();

        if (!$product->getSpecialPrice())
            return null;
            // return $this->mapDirectivePrice($params);

        $fieldData = $product->getFinalPrice();
        $fieldData = $this->cleanField($fieldData);

        return $fieldData;
    }

    protected function mapDirectiveSalePriceEffectiveDate($params = array())
    {
        $map = $params['map'];
        $product = $this->getProduct();

        $fieldData = "";
        if (!$this->hasSpecialPrice())
            return $fieldData;

        $cDate = Mage::app()->getLocale()->date(null, null, Mage::app()->getLocale()->getDefaultLocale());
        $timezone = Mage::app()->getStore($this->getStoreId())->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);

        $fromDate = new Zend_Date(null, null, Mage::app()->getLocale()->getDefaultLocale());
        if ($timezone) $fromDate->setTimezone($timezone);
        $fromDate->setDate(substr($product->getSpecialFromDate(), 0, 10), 'yyyy-MM-dd');
        $fromDate->setTime(substr($product->getSpecialFromDate(), 11, 8), 'HH:mm:ss');

        $toDate = new Zend_Date(null, null, Mage::app()->getLocale()->getDefaultLocale());
        if (!is_empty_date($product->getSpecialToDate())) {
            if ($timezone) $toDate->setTimezone($timezone);
            $toDate->setDate(substr($product->getSpecialToDate(), 0, 10), 'yyyy-MM-dd');
            $toDate->setTime('23:59:59', 'HH:mm:ss');
        } else {
            if ($timezone) $toDate->setTimezone($timezone);
            $toDate->setDate($cDate->toString('yyyy-MM-dd'), 'yyyy-MM-dd');
            $toDate->setTime('23:59:59', 'HH:mm:ss');
            $toDate->add(7, Zend_Date::DAY);
        }

        $fieldData = $fromDate->toString(Zend_Date::ISO_8601)."/".$toDate->toString(Zend_Date::ISO_8601);

        return $fieldData;
    }

    protected function mapDirectiveCurrency($params = array())
    {
        return $this->getData('store_currency_code');
    }

    protected function mapDirectiveAvailability($params = array())
    {
        $map = $params['map'];
        $product = $this->getProduct();

        $defaultVal = isset($map['default_value']) ? $map['default_value'] : "";

        if ($defaultVal != "")
        {
            $stock_status = $defaultVal;
            $stock_status = trim(strtolower($stock_status));

            if (false === array_search($stock_status,
                    $this->getConfig()->getAllowedStockStatuses()))
                $stock_status = $this->getConfig()->getOutOfStockStatus();

            $fieldData = $stock_status;
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
        $product = $this->getProduct();

        $defaultVal = isset($map['default_value']) ? $map['default_value'] : "";
        $defaultVal = trim(strtolower($defaultVal));

        if (false === array_search($defaultVal,
                $this->getConfig()->getAllowedConditions()))
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

        $map_by_category = $this->getConfig()->getMapCategorySorted(
            'product_type_by_category',
            $this->getStoreId()
        );

        $category_ids = $this->getProduct()->getCategoryIds();

        if (!empty($category_ids) && count($map_by_category) > 0)
        {
            foreach ($map_by_category as $arr)
            {
                if (array_search($arr['category'], $category_ids) !== false)
                {
                    $value = $arr['value'];
                    break;
                }
            }
        }

        if ($value != "")
            return html_entity_decode($value);

        $value = $this->getFieldValue($args);

        return html_entity_decode($value);
    }


    //
    // public::Tools
    //

    public function getFieldValue($args = array())
    {
        $value = "";
        $attName = $args['map']['attribute'];

        if ($this->getConfig()->isDirective($attName, $this->getStoreId()))
        {
            $attName = str_replace('df_directive_', '', $attName);
            $method = 'mapDirective' . $this->_camelize($attName);

            if (method_exists($this, $method))
                $value = $this->$method($args);
        }
        else
        {
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
            || $attribute->getFrontendInput() == 'multiselect')
        {
            if (!is_null($product->getResource()->getAttribute($attrCode)))
                $value = $product->getAttributeText($attrCode);
        }
        else
        {
            $value = $product->getData($attrCode);
        }

        return $value;
    }

    public function loadAssocIds($product, $storeId)
    {
        $assocIds = array();

        if ($product->getTypeId() != Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
            return false;

        $as = $this->getTools()->getChildsIds($product->getId());
        if ($as === false)
            return $assocIds;

        $as = $this->getTools()->getProductInStoresIds($as);
        foreach ($as as $assocId => $s)
        {
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

    public function getPrice()
    {
        return $this->getProduct()->getPrice();
    }

     public function calcMinimalPrice($product)
     {
        return $product->getMinimalPrice();
    }

    public function getSpecialPrice()
    {
        return $this->getProduct()->getSpecialPrice();
    }

    public function hasSpecialPrice()
    {
        $has = false;
        $product = $this->getProduct();

        if ($this->getSpecialPrice() <= 0)
            return $has;
        if (is_empty_date($product->getSpecialFromDate()))
            return $has;

        $cDate = Mage::app()->getLocale()->date(null, null, Mage::app()->getLocale()->getDefaultLocale());
        $timezone = Mage::app()->getStore($this->getStoreId())->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);

        $fromDate = new Zend_Date(null, null, Mage::app()->getLocale()->getDefaultLocale());
        if ($timezone) $fromDate->setTimezone($timezone);
        $fromDate->setDate(substr($product->getSpecialFromDate(), 0, 10), 'yyyy-MM-dd');
        $fromDate->setTime(substr($product->getSpecialFromDate(), 11, 8), 'HH:mm:ss');

        $toDate = new Zend_Date(null, null, Mage::app()->getLocale()->getDefaultLocale());
        if (!is_empty_date($product->getSpecialToDate())) {
            if ($timezone) $toDate->setTimezone($timezone);
            $toDate->setDate(substr($product->getSpecialToDate(), 0, 10), 'yyyy-MM-dd');
            $toDate->setTime('23:59:59', 'HH:mm:ss');
        } else {
            if ($timezone) $toDate->setTimezone($timezone);
            $toDate->setDate($cDate->toString('yyyy-MM-dd'), 'yyyy-MM-dd');
            $toDate->setTime('23:59:59', 'HH:mm:ss');
            $toDate->add(7, Zend_Date::DAY);
        }

        if (($fromDate->compare($cDate) == -1
                || $fromDate->compare($cDate) == 0)
            && ($toDate->compare($cDate) == 1
                || $toDate->compare($cDate) == 0))
        {
            $has = true;
        }

        return $has;
    }


    //
    // protected::Tools
    //

    protected function hasImage($product)
    {
        $image = $product->getData('image');

        if ($image != 'no_selection' && $image != "")
        {
            if (!file_exists($this->getData('images_path_prefix') . $image))
                return false;
        }
        else
        {
            return false;
        }

        return true;
    }

    protected function cleanField($field)
    {
        if (is_array($field))
        {
            foreach ($field as &$value)
            {
                $value = $this->cleanFieldValue($value);
                unset($value);
            }
        }
        else
        {
            $field = $this->cleanFieldValue($field);
        }

        return $field;
    }

    protected function cleanFieldValue($field)
    {
        // http://stackoverflow.com/questions/4224141/php-removing-invalid-utf-8-characters-in-xml-using-filter
        $valid_utf8 = '/([\x09\x0A\x0D\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})|./x';

        $field = preg_replace('#<br(\s?/)?>#i', ' ', $field);
        $field = strip_tags($field);
        $field = preg_replace('/[ ]{2,}/', ' ', $field);
        $field = trim($field);

        return preg_replace($valid_utf8, '$1', $field);
    }

    protected function _attributeDoesNotExist($attName)
    {
        Mage::throwException($attName . ' attribute does not exist!');
    }


    //
    // public::Config
    //

    public function getConfig()
    {
        return $this->getGenerator()->getConfig();
    }

    public function getConfigVar($key,
        $section = Doofinder_Feed_Model_Config::DEFAULT_SECTION)
    {
        return $this->getGenerator()->getConfigVar($key, null, $section);
    }

    public function getTools()
    {
        return $this->getGenerator()->getTools();
    }

    public function isSkip()
    {
        return $this->skip;
    }

    public function checkSkipSubmission()
    {
        return $this;
    }

    public function setFieldsMap($arr)
    {
        $this->_field_map = $arr;

        return $this;
    }
}
