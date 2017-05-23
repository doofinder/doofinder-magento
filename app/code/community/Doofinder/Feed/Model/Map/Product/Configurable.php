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
 * Configurable Product Map Model for Doofinder Feed
 *
 * @version    1.8.7
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Model_Map_Product_Configurable
    extends Doofinder_Feed_Model_Map_Product_Abstract
{
    protected static $_grouped = false;

    public static function setGrouped($v)
    {
        self::$_grouped = (bool)$v;
    }

    protected $_assoc_ids;
    protected $_assocs;
    protected $_cache_configurable_attribute_codes;

    public function _beforeMap()
    {
        $this->_assocs = array();
        $assocIds = $this->getAssocIds();

        $assoc = Mage::getModel('catalog/product');
        $assoc->setStoreId($this->getStoreId());

        $associatedProducts = $assoc
            ->getCollection()
            ->addIdFilter($assocIds)
            ->addAttributeToSelect('*')
            ->load();

        foreach ($associatedProducts as $associated)
        {
            $this->_assocs[$associated->getId()] = $associated;
        }

        $assocMapArr = array();
        foreach ($this->_assocs as $assoc)
        {
            $assocMap = $this->getAssocMapModel($assoc);

            if ($assocMap->checkSkipSubmission()->isSkip())
                continue;

            $assocMapArr[$assoc->getId()] = $assocMap;
        }

        $this->setAssocMaps($assocMapArr);

        return parent::_beforeMap();
    }

    public function _map()
    {
        $rows = array();
        // $grouped = ($this->getConfigVar('group_configurable_products') == 1);
        $grouped = self::$_grouped;

        $skipFields = array(
            'id',
            'title',
            'description',
            'price',
            'normal_price',
            'sale_price'
            );

        // Check if this product should be in the feed
        if (!$this->isSkip())
        {
            $masterData = parent::_map();
            reset($masterData);
            $masterData = current($masterData);

            // Only add the master data is we don't group products
            if ($grouped) {
                $rows[] = $masterData;
            } else {
                // Don't use parents availability
                $masterData['availability'] = null;
            }
        }

        // Map all child products
        foreach ($this->getAssocMaps() as $assocId => $assocMap)
        {
            if (!$assocMap->isSkip())
            {
                $row = $assocMap->map();
                reset($row);
                $row = current($row);

                // We can group multiple configurable products into the master product
                if (!$grouped)
                {
                    foreach ($row as $name => $value)
                    {
                        if (in_array($name, $skipFields))
                        {
                            continue;
                        }

                        $masterData = $this->_mapGrouped($name, $value, $masterData);
                    }
                }
                else
                {
                    $rows[] = $row; // Add each product as separate product
                }
            }
        }

        if (!$grouped) {
            // Make sure boost field has single value
            if (isset($masterData['boost']) && is_array($masterData['boost'])) {
                $masterData['boost'] = max($masterData['boost']);
            }
            // Make sure availability has single value
            if (isset($masterData['availability']) && is_array($masterData['availability'])) {
                if (in_array($this->getConfig()->getInStockStatus(), $masterData['availability'])) {
                    $masterData['availability'] = $this->getConfig()->getInStockStatus();
                } else {
                    $masterData['availability'] = $this->getConfig()->getOutOfStockStatus();
                }
            }

            $rows[] = $masterData; // Add the complete master data object
        }

        return $rows;
    }

    protected function _mapGrouped($name, $childValue, $masterData)
    {
        $value = $masterData[$name];

        if (!is_array($value)) {
            $value = array($value);
        }
        if (!is_array($childValue)) {
            $childValue = array($childValue);
        }

        $value = array_merge($value, $childValue);

        // Remove duplicates
        $value = array_values(array_unique($value));

        // Remove array if value is single
        if (count($value) == 1) {
            $value = $value[0];
        }

        $masterData[$name] = $value;
        return $masterData;
    }

    public function getAssocIds()
    {
        if (is_null($this->_assoc_ids))
            $this->_assoc_ids = $this->loadAssocIds(
                $this->getProduct(),
                $this->getStoreId()
            );

        asort($this->_assoc_ids);

        return $this->_assoc_ids;
    }

    protected function getAssocMapModel($oProduct)
    {
        $params = array(
            'store_code' => $this->getData('store_code'),
            'store_id' => $this->getData('store_id'),
            'website_id' => $this->getData('website_id'),
        );

        $productMap = Mage::getModel('doofinder_feed/map_product_associated',
                                     $params);

        $productMap->setGenerator($this->getGenerator())
            ->setProduct($oProduct)
            ->setFieldsMap($this->_field_map)
            ->setParentMap($this)
            ->initialize();

        return $productMap;
    }

    public function getConfigurableAttributeCodes()
    {
        if (is_null($this->_cache_configurable_attribute_codes))
            $this->_cache_configurable_attribute_codes = $this->getTools()
                ->getConfigurableAttributeCodes($this->getProduct()->getId());

        return $this->_cache_configurable_attribute_codes;
    }
}
