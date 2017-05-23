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
 * Generator model for Doofinder Feed
 *
 * @version    1.8.7
 * @package    Doofinder_Feed
 */
if (!defined('DS'))
    define('DS', DIRECTORY_SEPARATOR);

class Doofinder_Feed_Model_Generator extends Varien_Object
{
    const DEFAULT_BATCH_SIZE = 100;
    const PRODUCT_ELEMENT = 'item';
    const CATEGORY_SEPARATOR = '%%';
    const CATEGORY_TREE_SEPARATOR = '>';
    const VALUE_SEPARATOR = '/';


    protected $_badChars = array('"',"\r\n","\n","\r","\t", "|");
    protected $_repChars = array(""," "," "," "," ", "");

    protected $_store;
    protected $_oRootCategory;

    protected $_maxProductId;

    protected $_attributes = array();
    protected $_categories = array();
    protected $_fieldMap;

    protected $_iDumped = 0;
    protected $_iSkipped = 0;

    protected $_oXmlWriter;

    protected $_response;

    protected $_errors = array();

    protected $_lastProcessedProductId;

    /**
     * Log to doofinder generator logfile
     *
     * @param string $message
     * @param integer $level
     */
    public function logError($message)
    {
        $this->_errors[] = $message;
    }

    /**
     * Log to doofinder generator logfile only once
     *
     * @param string $message
     */
    public function logErrorOnce($message)
    {
        if (!in_array($message, $this->getErrors())) {
            $this->logError($message);
        }
    }

    //
    // public::Export
    //

    public function run()
    {
        // This must NOT depend on cron being enabled because it's used
        // by the front controller!!!

        Doofinder_Feed_Model_Map_Product_Configurable::setGrouped($this->getData('grouped'));
        // Some config
        $this->_oRootCategory = $this->getRootCategory();

        // Generate Feed
        $this->_loadAdditionalAttributes();
        $this->_maxProductId = $this->getMaxProductId();

        // Clear errors
        $this->_errors = array();

        // Perform run
        $this->_initFeed();
        $this->_batchProcessProducts(
            $this->getData('_offset_'),
            $this->getData('_limit_')
        );

        // Only close feed if close empty flag is set to true or there was at least one processed product
        if ($this->getData('close_empty') || $this->getLastProcessedProductId() != $this->getData('_offset_')) {
            $this->_closeFeed();
        }

        return $this->_response;
    }

    public function getSQL()
    {
        return $this->_getProductCollection(
            $this->getData('_offset_'), $this->getData('_limit_')
        )->getSelect()->assemble();
    }

    public function getProductCount()
    {
        return $this->_getProductCollection()->getSize();
    }

    public function getMaxProductId()
    {
        $collection = $this->_getProductCollection();
        $collection->getSelect()->limit(1);
        $collection->getSelect()->order('e.entity_id DESC');
        $item = $collection->fetchItem();

        return $item ? $item->getEntityId() : 0;
    }

    /**
     * Is the feed done, are there any products
     * left to process.
     *
     * @return boolean
     */
    public function isFeedDone()
    {
        return $this->_lastProcessedProductId >= $this->_maxProductId;
    }

    /**
     * Get the ID of the last processed product.
     *
     * @return integer
     */
    public function getLastProcessedProductId()
    {
        return $this->_lastProcessedProductId;
    }

    /**
     * Get generator progress, it is what part
     * of products has been processed yet.
     *
     * @return double
     */
    public function getProgress()
    {
        $collection = $this->_getProductCollection();

        $all = $collection->getSize();

        $collection = $this->_getProductCollection();
        $collection->addAttributeToFilter('entity_id', array('lteq' => $this->_lastProcessedProductId));
        $now = $collection->getSize();

        return $now / $all;
    }

    public function addProductToFeed($args)
    {
        try
        {
            $row = $args['row'];

            $this->_lastProcessedProductId = $row['entity_id'];

            $parentEntityId = null;

            $map = $this->_getProductMapModel($row['type_id'], array());

            if (is_null($map)) {
                Mage::throwException("There is no map definition for product with type {$row['type_id']}");
            }


            $product = Mage::getModel('catalog/product');
            $product->setData($row)
                ->setStoreId($this->getStoreId())
                ->setCustomerGroupId($this->getData('customer_group_id'));

            $product->getResource()->load($product, $row['entity_id']);
            $map->setGenerator($this)
                ->setProduct($product)
                ->setFieldsMap($this->_getFieldsMap())
                ->initialize();

            if ($map->checkSkipSubmission()->isSkip())
                return;

            if ($this->_addProductToXml($map))
                $this->_iDumped++;

            $map->unsetData();
        }
        catch (Exception $e)
        {
            $this->logError('Error processing product (ID: ' . $row['entity_id'] . '): ' . $e->getMessage(), Zend_Log::ERR);
        }
    }


    //
    // protected::Export
    //

    protected function _batchProcessProducts($offset, $limit)
    {
        // Make sure we have this initialized
        // in case of an empty collection
        $this->_lastProcessedProductId = $offset;

        $collection = $this->_getProductCollection($offset, $limit);

        Mage::getSingleton('core/resource_iterator')->walk(
            $collection->getSelect(),
            array(array($this, 'addProductToFeed'))
        );
        $this->_flushFeed();

    }

    protected function _addProductToXml(
        Doofinder_Feed_Model_Map_Product_Abstract $productMap)
    {

        $iDumped = 0;
        $displayPrice = $this->getDisplayPrice();

        try
        {
            if ($productMap->isSkip())
            {
                $this->_iSkipped++;
                return $this;
            }

            $productData = $productMap->map();

            if ($productMap->getProduct()->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE
                && $productMap->hasAssocMaps()
                && $productMap->getIsVariants())
            {
                foreach ($productMap->getAssocMaps() as $assocMap)
                    if ($assocMap->isSkip())
                        $this->_iSkipped++;
            }

            if (($iProducts = count($productData)) > 1)
            {
                $productData[0] = array_filter($productData[0]);
                // $productData[0]['assoc_id'] = $productData[0]['id'];

                for ($i = 1; $i < $iProducts; $i++)
                {
                    $productData[$i] = array_merge(
                        $productData[0],
                        array_filter($productData[$i])
                    );
                    // $productData[$i]['assoc_id'] = $productData[0]['id'];
                }
            }

            foreach ($productData as $data)
            {
                $this->_oXmlWriter->startElement(self::PRODUCT_ELEMENT);

                if (!isset($data['description']))
                {
                    if (isset($data['long_description'])) {
                        $data['description'] = $data['long_description'];
                        unset($data['long_description']);
                    } else {
                        $data['description'] = '';
                    }
                }

                krsort($data);

                foreach ($data as $field => $value)
                {

                    if (!is_array($value))
                    {
                        $value = trim($value);
                    }

                    if ($field != 'description' && empty($value))
                    {
                        continue;
                    }

                    if (!$displayPrice && ($field === 'price' || $field === 'sale_price'))
                    {
                        continue;
                    }

                    $this->_oXmlWriter->startElement($field);

                    // Make sure $value is a flat array
                    if (!is_array($value))
                    {
                        $value = array($value);
                    }
                    else if (!$this->_isArrayFlat($value))
                    {
                        $this->logErrorOnce("Value of $field field is a multidimensional array, encoded value: " . json_encode($value));
                        $value = $this->_flattenArray($value);
                    }

                    $value = implode(self::VALUE_SEPARATOR, array_filter($value));

                    $written = @$this->_oXmlWriter->writeCData($value);
                    if ( ! $written )
                    {
                        $this->_oXmlWriter->writeComment("Cannot write the value for the $field field.");

                        $this->logErrorOnce("Cannot write the value for the $field field, encoded value: " . json_encode($value));
                    }

                    $this->_oXmlWriter->endElement();
                }

                $this->_oXmlWriter->endElement();
                $this->_flushFeed(true);

                $iDumped++;
            }
        }
        catch (Exception $e)
        {
            if ($this->getConfigVar('debug') == 1)
                $this->_debug($e->getMessage());
        }

        return $iDumped > 0;
    }


    //
    // public::Configuration
    //

    public function getContentType()
    {
        return self::CONTENT_TYPE;
    }

    public function getConfig()
    {
        return Mage::getSingleton('doofinder_feed/config');
    }

    public function getConfigVar($key, $storeId = null,
        $section = Doofinder_Feed_Model_Config::DEFAULT_SECTION)
    {
        return $this->getConfig()->getConfigVar($key, $storeId, $section);
    }


    //
    // public::Tools
    //

    public function getStore()
    {
        if (is_null($this->_store))
            $this->_loadStore();

        return $this->_store;
    }

    public function getStoreId()
    {
        return $this->getStore()->getStoreId();
    }

    public function getStoreCode()
    {
        return $this->getStore()->getCode();
    }

    public function getWebsiteId()
    {
        return $this->getStore()->getWebsiteId();
    }

    public function getErrors()
    {
        return $this->_errors;
    }

    public function getRootCategory()
    {
        if (is_null($this->_oRootCategory))
        {
            $this->_oRootCategory = Mage::getModel('catalog/category')->load(
                $this->getStore()->getRootCategoryId()
            );
        }

        return $this->_oRootCategory;
    }

    public function getCategories($product)
    {
        $categories = array();

        $prodCategories = Mage::getResourceModel('catalog/category_collection')
            ->addIdFilter($product->getCategoryIds())
            ->addFieldToFilter('path', array('like' => $this->_oRootCategory->getPath() . '/%'))
            ->addFieldToFilter('is_active', array('eq'=>'1'));

        $include_in_menu = Mage::getStoreConfig(
            'doofinder_cron/feed_settings/categories_in_navigation',
            $this->getStoreId()
        );

        if($include_in_menu == 1) {
            $prodCategories->addFieldToFilter('include_in_menu', array('eq'=> '1'));
        }

        $prodCategories = $prodCategories->getItems();

        $prodCategories = array_keys($prodCategories);

        foreach ($prodCategories as $id)
        {
            if (isset($this->_categories[$id]))
                $tree = $this->_categories[$id];
            else
                $tree = $this->_getCategoryTree($id);

            if (strlen($tree))
                $categories[] = $tree;
        }

        sort($categories);

        $nbcategories = count($categories);
        $result = array();

        for ($i = 1; $i < $nbcategories; $i++)
        {
          if (strpos($categories[$i], $categories[$i - 1]) === 0)
            continue;
          $result[] = $this->_cleanFieldValue($categories[$i - 1]);
        }

        if (!empty($categories[$i - 1]))
          $result[] = $this->_cleanFieldValue($categories[$i - 1]);

        return $result;
    }

    /**
     *  Get all parent category names (including itself) for selected category ID
     *  @param int $catId Category ID
     *  @return string Category names concat'd by CATEGORY_TREE_SEPARATOR
     */
    protected function _getCategoryTree($catId)
    {
        $category = Mage::getModel('catalog/category')->load($catId);
        $tree = array();

        $path = $category->getPath();
        $ids = explode('/', $path);

        unset($ids[0]);

        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->setStoreId($this->getStoreId())
            ->addIdFilter($ids)
            ->addAttributeToSort('path', 'asc')
            ->addAttributeToSelect('*');

        foreach ($categories as $category)
        {
            if ($category->getId() != $this->_oRootCategory->getId())
            {
                if (strlen($category->getName()))
                {
                    $tree[] = strip_tags($category->getName());
                }
            }
        }

        $tree = $this->_sanitizeData($tree);
        $tree = implode(self::CATEGORY_TREE_SEPARATOR, $tree);
        $this->_categories[$catId] = $tree;

        return $this->_categories[$catId];
    }

    public function getAttribute($attrCode)
    {
        if (isset($this->_attributes[$attrCode]))
            return $this->_attributes[$attrCode];

        return false;
    }

    public function getTools()
    {
        return Mage::getSingleton('doofinder_feed/tools');
    }


    //
    // protected::Output
    //

    protected function _initFeed()
    {
        $this->_oXmlWriter = new XMLWriter();
        $this->_oXmlWriter->openMemory();
        if (!$this->getData('_offset_'))
        {
            $this->_oXmlWriter->startDocument('1.0', 'UTF-8');

            // Output the parent rss tag
            $this->_oXmlWriter->startElement('rss');
            $this->_oXmlWriter->writeAttribute('version', '2.0');

            $this->_oXmlWriter->startElement('channel');
            $this->_oXmlWriter->writeElement('title', 'Product feed');
            $this->_oXmlWriter->startElement('link');
            $this->_oXmlWriter->writeCData(Mage::getBaseUrl().'doofinder/feed');
            $this->_oXmlWriter->endElement();
            $this->_oXmlWriter->writeElement('pubDate', strftime('%a, %d %b %Y %H:%M:%S %Z'));
            $this->_oXmlWriter->writeElement('generator', 'Doofinder/'.Mage::getConfig()->getModuleConfig("Doofinder_Feed")->version);
            $this->_oXmlWriter->writeElement('description', 'Magento Product feed for Doofinder');

            $this->_flushFeed();
        }
    }

    protected function _flushFeed($break = false)
    {
        $this->_response .= $this->_oXmlWriter->flush(true);

        if ($break)
        {
          $this->_response .= PHP_EOL;
        }
    }

    protected function _closeFeed()
    {
        if ($this->isFeedDone())
        {
            if (!$this->getData('_offset_'))
            {
                $this->_oXmlWriter->endElement(); // Channel
                $this->_oXmlWriter->endElement(); // RSS
                $this->_oXmlWriter->endDocument();

                $this->_flushFeed();
            } else
            {
                $this->_response .= '</channel></rss>';
            }
        }
    }

    protected function _debug($m)
    {
        // $this->_response .= '<pre>';
        // var_dump($m);
        // $this->_response .= '</pre>';
    }

    protected function _sanitizeData($data)
    {
        $sanitized = array();

        foreach ($data as $key => $value)
            $sanitized[$key] = str_replace($this->_badChars,
                                           $this->_repChars,
                                           $value);

        return $sanitized;
    }

    protected function _addProductTypeToFilter($collection)
    {
        $disabled = array_diff(
            array(
                Mage_Catalog_Model_Product_Type::TYPE_BUNDLE,
                Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE,
                Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE,
                Mage_Catalog_Model_Product_Type::TYPE_GROUPED,
                Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
                Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
            ),
            explode(',', $this->getConfigVar('product_types'))
        );

        // Check if we should disable specific types
        if (count($disabled) > 0)
            $collection->addAttributeToFilter('type_id',
                                              array('nin' => $disabled));

        return $collection;
    }


    //
    // protected::Tools
    //

    protected function _loadStore()
    {
        if (!$this->hasData('store_code'))
            $this->setData('store_code', Mage_Core_Model_Store::DEFAULT_CODE);

        try
        {
            $this->_store = Mage::app()->getStore($this->getData('store_code'));
        }
        catch (Exception $e)
        {
            $e->setMessage('Invalid Store Code.');
            $this->_stopOnException($e);
        }
    }

    protected function _loadAdditionalAttributes()
    {
        $storeId = $this->getStoreId();

        $attributeCodes = $this->getConfig()
            ->getMultipleSelectVar('additional_attributes', $storeId);
        $model = Mage::getModel('catalog/product')->setStoreId($storeId);

        foreach ($attributeCodes as $attrCode)
        {
            $attribute = $model->getResource()->getAttribute($attrCode);
            $this->_attributes[$attribute->getAttributeCode()] = $attribute;
        }
    }

    protected function _getProductCollection($offset = 0, $limit = 0)
    {
        $collection = $this->getProductCollection($offset, $limit);

        if (count($this->getProducts()))
            $collection->addAttributeToFilter('entity_id', array('in' => $this->getProducts()));

        if ($limit && $limit > 0)
            $collection->getSelect()->limit($limit, 0);

        if ($offset)
            $collection->addAttributeToFilter('entity_id', array('gt' => $offset));


        return $collection;
    }

    public function getProductCollection()
    {
        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addStoreFilter($this->getStoreId());

        $this->_addProductTypeToFilter($collection);

        $collection->addAttributeToFilter('status', 1);
        $collection->addAttributeToFilter('visibility', array(
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH
        ));
        $collection->addAttributeToSelect('*');

        return $collection;
    }

    protected function _getProductMapModel($typeId, $args = array())
    {
        $isAssoc = isset($args['is_assoc']) && $args['is_assoc'] ? true : false;

        switch ($typeId)
        {
            case 'simple':
                if ($isAssoc)
                    $model = 'doofinder_feed/map_product_associated';
                else
                    $model = 'doofinder_feed/map_product_simple';
                break;

            case 'abstract':
            case 'bundle':
            case 'configurable':
            case 'downloadable':
            case 'grouped':
            case 'virtual':
                $model = 'doofinder_feed/map_product_'.$typeId;
                break;

            default:
                return null;
        }

        return Mage::getModel($model, array(
            'store_code' => $this->getStoreCode(),
            'store_id' => $this->getStoreId(),
            'website_id' => $this->getWebsiteId(),
        ));
    }

    protected function _getFieldsMap()
    {
        if (!is_null($this->_fieldMap))
            return $this->_fieldMap;

        $product = Mage::getModel('catalog/product')
            ->setStoreId($this->getStoreId());


        $this->_fieldMap = array();

        $fields = $this->getConfigVar('fields');

        $map = Mage::getStoreConfig('doofinder_cron/attributes_mapping', $this->getStore());
        $additional = array();
        if (isset($map['additional'])) {
            $additional = unserialize($map['additional']);
        }

        unset($map['additional']);

        if (!empty($additional['additional_mapping']))
        {
            foreach ($additional['additional_mapping'] as $data)
            {
                if (isset($map[$data['field']])) continue;

                $fields[$data['field']] = array('label' => $data['label']);
                $map[$data['field']] = $data['attribute'];
            }
        }

        foreach ($map as $key => $attName)
        {
            if (!isset($fields[$key])) continue;

            if (!$this->getConfig()->isDirective($attName,
                                                 $this->getStoreId()))
            {
                $att = $product->getResource()->getAttribute($attName);

                if ($att === false)
                {
                    continue;
                }

                $att->setStoreId($this->getStoreId());
                $this->_attributes[$att->getAttributeCode()] = $att;
            }

            $this->_fieldMap[$key] = array(
                'label' => $fields[$key]['label'],
                'attribute' => $attName,
                'field' => $key,
            );
        }
        return $this->_fieldMap;
    }

    protected function _stopOnException(Exception $e)
    {
        Mage::logError($e->getMessage());
    }

    protected function _cleanFieldValue($field)
    {
        // http://stackoverflow.com/questions/4224141/php-removing-invalid-utf-8-characters-in-xml-using-filter
        $valid_utf8 = '/([\x09\x0A\x0D\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})|./x';

        $field = preg_replace('#<br(\s?/)?>#i', ' ', $field);
        $field = strip_tags($field);
        $field = preg_replace('/[ ]{2,}/', ' ', $field);
        $field = trim($field);
        $exField = explode(self::CATEGORY_TREE_SEPARATOR, $field);
        $newField = array();
        foreach ($exField as $el) {
            $newField[] = html_entity_decode($el, null, 'UTF-8');
        }
        $field = implode(self::CATEGORY_TREE_SEPARATOR, $newField );

        return preg_replace($valid_utf8, '$1', $field);
    }

    /**
     * Check if array is flat (not multidimensional)
     *
     * @param array $arr
     * @return boolean
     */
    protected function _isArrayFlat(array $arr)
    {
        $isFlat = true;

        foreach ($arr as $item)
        {
            if (is_array($item))
            {
                $isFlat = false;
                break;
            }
        }

        return $isFlat;
    }

    /**
     * Flatten array recursively
     *
     * @notice This requires PHP5.3+
     *
     * @param array @arr
     * @return array
     */
    protected function _flattenArray(array $arr) {
        $flattenedArray = array();
        array_walk_recursive($arr, function($item) use (&$flattenedArray) { $flattenedArray[] = $item; });
        return $flattenedArray;
    }
}
