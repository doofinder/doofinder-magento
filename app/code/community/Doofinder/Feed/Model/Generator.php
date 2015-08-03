<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.5.3
 */

/**
 * Generator model for Doofinder Feed
 *
 * @version    1.5.3
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

    protected $_iProductCount;

    protected $_attributes = array();
    protected $_categories = array();
    protected $_fieldMap;

    protected $_iBatchSize = 0;
    protected $_iDumped = 0;
    protected $_iSkipped = 0;

    protected $_oXmlWriter;

    protected $_response;

    protected $_errors = array();

    //
    // public::Export
    //

    public function run()
    {
        if ($this->getConfigVar('enabled') != 1)
            return;

        Doofinder_Feed_Model_Map_Product_Configurable::setGrouped($this->getData('grouped'));
        // Some config
        $this->_oRootCategory = $this->getRootCategory();

        // Generate Feed
        $this->_loadAdditionalAttributes();
        $this->_iProductCount = $this->getProductCount();

        // Clear errors
        $this->_errors = array();

        if ($this->getData('_offset_') >= $this->_iProductCount)  // offset is 0-based
        {
            return "";
        }
        else
        {
            $this->_initFeed();
            if (! $this->getData('_limit_'))
            {
                $this->_iBatchSize = false;

                // Dump ALL products
                for ($offset = $this->getData('_offset_');
                        $offset < $this->_iProductCount;
                        $offset += self::DEFAULT_BATCH_SIZE)
                    $this->_batchProcessProducts($offset, self::DEFAULT_BATCH_SIZE);
            }
            else
            {
                $this->_iBatchSize = $this->_batchProcessProducts(
                    $this->getData('_offset_'),
                    $this->getData('_limit_')
                );
            }
            $this->_closeFeed();
            return $this->_response;
        }
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


    public function addProductToFeed($args)
    {
        try
        {
            $row = $args['row'];

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
            Mage::logException($e);
            $this->_errors[] = $e->getMessage();
        }
    }


    //
    // protected::Export
    //

    protected function _batchProcessProducts($offset, $limit)
    {
        $batchSize = min($this->_iProductCount - $offset, $limit);

        if ($batchSize > 0)
        {
            $collection = $this->_getProductCollection($offset, $batchSize);
            Mage::getSingleton('core/resource_iterator')->walk(
                $collection->getSelect(),
                array(array($this, 'addProductToFeed'))
            );
            $this->_flushFeed();
        }
        else
        {
            $batchSize = 0;
        }

        return $batchSize;
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

                    if ($field != 'description' && empty($value)) {
                        continue;
                    }

                    if (!$displayPrice && $field === 'price') {

                        continue;
                    }

                    $this->_oXmlWriter->startElement($field);

                    if ($field != 'categories')
                    {
                        if (!is_array($value))
                            $value = array($value);

                        $value = implode(self::VALUE_SEPARATOR, array_filter($value));
                    }



                    $written = @$this->_oXmlWriter->writeCData($value);
                    if ( ! $written )
                    {
                        $this->_oXmlWriter->writeComment("Cannot write the value for the $field field.");
                    }

                    $this->_oXmlWriter->endElement();
                }

                $this->_oXmlWriter->endElement();

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
            ->addFieldToFilter('is_active', array('eq'=>'1'))
            ->getItems();
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
          $result[] = $categories[$i - 1];
        }

        if (!empty($categories[$i - 1]))
          $result[] = $categories[$i - 1];

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
            ->addIdFilter($ids)
            ->addAttributeToSort('path', 'asc')
            ->addAttributeToSelect('*');

        foreach ($categories as $category)
        {
            if ($category->getId() != $this->_oRootCategory->getId())
            {
                if (strlen($category->getName()))
                {
                    $tree[] = $category->getName();
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
        if ($this->getData('_offset_') === 0)
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

    protected function _flushFeed()
    {
        $this->_response .= $this->_oXmlWriter->flush(true);
    }

    protected function _closeFeed()
    {
        if (! $this->getData('_limit_'))
        {
            $this->_oXmlWriter->endElement(); // Channel
            $this->_oXmlWriter->endElement(); // RSS
            $this->_oXmlWriter->endDocument();

            $this->_flushFeed();
        }
        else
        {
            if ($this->getData('_offset_') < $this->_iProductCount
                && ($this->getData('_offset_') + $this->getData('_limit_')) >= $this->_iProductCount)
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

    protected function _getProductCollection($offset = 0, $limit = null)
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

        if (!is_null($limit))
            $collection->getSelect()->limit($limit, $offset);

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
        $tmp = $cfg = $this->getConfigVar('field_map');

        foreach ($tmp as $key => $mapData)
        {
            $attName = $mapData['attribute'];
            if (!$this->getConfig()->isDirective($attName,
                                                 $this->getStoreId()))
            {
                $att = $product->getResource()->getAttribute($attName);

                if ($att === false)
                {
                    unset($cfg[$key]);
                    continue;
                }

                $att->setStoreId($this->getStoreId());
                $this->_attributes[$att->getAttributeCode()] = $att;
            }
        }

        foreach ($cfg as $mapData)
            $this->_fieldMap[$mapData['field']] = $mapData;

        return $this->_fieldMap;
    }

    protected function _stopOnException(Exception $e)
    {
        Mage::log($e->getMessage());
    }
}
