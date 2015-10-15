<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.5.8
 */

/**
 * Configurable Product Map Model for Doofinder Feed
 *
 * @version    1.5.8
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
            if ($grouped)
                $rows[] = $masterData;
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

        if (!$grouped)
            $rows[] = $masterData; // Add the complete master data object

        return $rows;
    }

    protected function _mapGrouped($name, $value, $masterData)
    {
        if (!is_array($masterData[$name]))
        {
            if ($masterData[$name] != $value)
            {
                if (strlen($masterData[$name]))
                    $masterData[$name] = array(
                        $masterData[$name],
                        $value
                    );
                else
                    $masterData[$name] = $value;
            }
        }
        else
        {
            if (!in_array($value, $masterData[$name]))
                $masterData[$name][] = $value;
        }
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
