<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.33
 */

/**
 * Configurable Product Map Model for Doofinder Feed
 *
 * @version    1.8.33
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Model_Map_Product_Configurable
    extends Doofinder_Feed_Model_Map_Product_Abstract
{
    protected static $_grouped = false;

    public static function setGrouped($arg)
    {
        self::$_grouped = (bool) $arg;
    }

    protected $_assocIds;
    protected $_assocs;
    protected $_attributeCache;

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

        foreach ($associatedProducts as $associated) {
            $this->_assocs[$associated->getId()] = $associated;
        }

        $assocMapArr = array();
        foreach ($this->_assocs as $assoc) {
            $assocMap = $this->getAssocMapModel($assoc);

            if ($assocMap->checkSkipSubmission()->isSkip())
                continue;

            $assocMapArr[$assoc->getId()] = $assocMap;
        }

        $this->setAssocMaps($assocMapArr);

        return parent::_beforeMap();
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    // @codingStandardsIgnoreStart
    public function _map()
    {
    // @codingStandardsIgnoreEnd
        $rows = array();
        $grouped = self::$_grouped;

        $skipFields = array(
            'id',
            'title',
            'description',
            'price',
            'normal_price',
            'sale_price',
            'image_link'
            );

        // Check if this product should be in the feed
        if (!$this->isSkip()) {
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
        foreach ($this->getAssocMaps() as $assocMap) {
            if (!$assocMap->isSkip()) {
                $row = $assocMap->map();
                reset($row);
                $row = current($row);

                // We can group multiple configurable products into the master product
                if (!$grouped) {
                    foreach ($row as $name => $value) {
                        if (in_array($name, $skipFields)) {
                            continue;
                        }

                        $masterData = $this->_mapGrouped($name, $value, $masterData);
                    }
                } else {
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
        if ($this->_assocIds === null)
            $this->_assocIds = $this->loadAssocIds(
                $this->getProduct(),
                $this->getStoreId()
            );

        asort($this->_assocIds);

        return $this->_assocIds;
    }

    protected function getAssocMapModel($oProduct)
    {
        $params = array(
            'store_code' => $this->getData('store_code'),
            'store_id' => $this->getData('store_id'),
            'website_id' => $this->getData('website_id'),
        );

        $productMap = Mage::getModel('doofinder_feed/map_product_associated', $params);

        $productMap->setGenerator($this->getGenerator())
            ->setProduct($oProduct)
            ->setFieldsMap($this->_fieldMap)
            ->setParentMap($this)
            ->initialize();

        return $productMap;
    }

    public function getConfigurableAttributeCodes()
    {
        if ($this->_attributeCache === null)
            $this->_attributeCache = $this->getTools()
                ->getConfigurableAttributeCodes($this->getProduct()->getId());

        return $this->_attributeCache;
    }
}
