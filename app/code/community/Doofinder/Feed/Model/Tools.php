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
 * Tools model for Doofinder Feed
 *
 * @version    1.8.7
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Model_Tools extends Varien_Object
{
    public function _construct()
    {
        parent::_construct();
        $this->loadEntityType('catalog_product');
    }

    public function loadEntityType($type)
    {
        if (is_array($type))
        {
            foreach ($type as $t)
                if (is_string($t))
                    $this->loadEntityType($t);
        }
        else
        {
            $entityType = Mage::getModel('eav/config')->getEntityType('catalog_product');

            Mage::unregister('doofinder_feed/entity_type/'.$type);
            Mage::register('doofinder_feed/entity_type/'.$type, $entityType);
        }
        return $this;
    }

    public function getEntityType($type)
    {
        return Mage::registry('doofinder_feed/entity_type/'.$type);
    }

    public function getProductAttributeValueBySql($attribute, $type = "text", $productId, $storeId = null, $strict = false, $debug = false)
    {
        if (array_search($type, array('text', 'int', 'decimal', 'varchar', 'datetime')) === false)
        {
            Mage::throwException(sprintf("Unknown attribute backend type %s for attribute code %s.", $type, $attribute->getAttributeCode()));
        }

        if (is_null($storeId))
        {
            return $this->getProductAttributeValueBySql($attribute, $type, $productId, Mage_Core_Model_App::ADMIN_STORE_ID, true, $debug);
        }

        $attributeId = $attribute->getAttributeId();

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = $conn->select()
            ->from(array('val' => $this->getRes()->getTableName('catalog/product')."_".$type),
                array('value'))
            ->joinInner(array('eav' => $this->getRes()->getTableName('eav/attribute')),
                'val.attribute_id=eav.attribute_id',
                array())
            ->where('val.entity_id = ?', $productId)
            ->where('val.entity_type_id = ?', $this->getEntityType('catalog_product')->getEntityTypeId())
            ->where('val.store_id = ?', $storeId)
            ->where('val.attribute_id = ?', $attributeId);

        $value = $this->getConnRead()->fetchCol($query);
        if (is_array($value) && @$value[0] === null)
            $value = null;
        elseif (is_array($value) && isset($value[0]))
            $value = $value[0];
        else if (is_array($value) && count($value) == 0)
            $value = null;

        if (is_null($value) && $storeId != Mage_Core_Model_App::ADMIN_STORE_ID && $strict === false)
        {
            return $this->getProductAttributeValueBySql($attribute, $type, $productId, Mage_Core_Model_App::ADMIN_STORE_ID, true, $debug);
        }

        return $value;
    }

    /**
     * Check if there is a parent of type (configurable, ..)
     *
     * @param string $type_id
     * @param string $sku
     * @param string $parent_type_id
     * @return array|false
     */
    public function isChildOfProductType($type_id, $sku, $parent_type_id)
    {
        $data = false;

        if ($type_id != Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            return $data;

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = $conn->select()
            ->from(array('cpe' => $this->getRes()->getTableName('catalog/product')),
                array('entity_id' => 'cpe.entity_id',
                    'sku' => 'cpe.sku',
                    'parent_entity_id' => 'cpe_parent.entity_id',
                    'parent_sku' => 'cpe_parent.sku'))
            ->joinInner(array('cpsl' => $this->getRes()->getTableName('catalog/product_super_link')),
                'cpe.entity_id = cpsl.product_id',
                array())
            ->joinInner(array('cpe_parent' => $this->getRes()->getTableName('catalog/product')),
                'cpsl.parent_id = cpe_parent.entity_id',
                array())
            ->where('cpe.sku', $sku)
            ->where('cpe_parent.type_id', $parent_type_id);

        $result = $this->getConnRead()->fetchRow($query);

        if ($result !== false)
        {
            $data = $result;
        }

        return $data;
    }

    public function getProductAttributeSelectValue($attribute, $valueId, $storeId = null, $strict = false, $debug = false)
    {
        if (is_null($storeId))
        {
            return $this->getProductAttributeSelectValue($attribute, $valueId, Mage_Core_Model_App::ADMIN_STORE_ID, true, $debug);
        }

        $attributeId = $attribute->getAttributeId();

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = $conn->select()
            ->from($this->getRes()->getTableName('eav/attribute_option'),
                array('opt'))
            ->where('opt.option_id = ?', $valueId)
            ->where('opt.attribute_id = ?', $attributeId)
            ->where('opt.store_id = ?', $storeId);

        $value = $this->getConnRead()->fetchCol($query);
        if (is_array($value) && @$value[0] === null)
            $value = null;
        elseif (is_array($value) && isset($value[0]))
            $value = $value[0];
        else if (is_array($value) && count($value) == 0)
            $value = null;

        if (is_null($value) && $storeId != Mage_Core_Model_App::ADMIN_STORE_ID && $strict === false)
        {
            return $this->getProductAttributeSelectValue($attribute, $valueId, Mage_Core_Model_App::ADMIN_STORE_ID, true, $debug);
        }

        return $value;
    }

    /**
     * Get categories ids by product id.
     *
     * @param string $type_id
     * @param string $sku
     * @param string $parent_type_id
     * @return array|false
     */
    public function getCategoriesById($productId)
    {
        $data = false;

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = $conn->select()
            ->from($this->getRes()->getTableName('catalog/category_product'),
                array('category_id'))
            ->where('product_id = ?', $productId);

        $result = $this->getConnRead()->fetchAll($query);

        if ($result !== false)
        {
            $data = array();
            foreach ($result as $k => $row)
                $data[] = $row['category_id'];
        }
        return $data;
    }

    /**
     * Gets stores ids of product(s).
     * @param int|array $productId
     * @return array()
     */
    public function getProductInStoresIds($productId)
    {

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');

        if (is_array($productId))
        {
            $value = array();
            foreach ($productId as $pid)
                $value[$pid] = array();

            $query = $conn->select()
                ->from(array('pw' => $this->getRes()->getTableName('catalog/product_website')),
                    array('product_id' => 'pw.product_id',
                        'store_id' => 's.store_id'))
                ->joinInner(array('s' => $this->getRes()->getTableName('core/store')),
                    's.website_id = pw.website_id',
                    array())
                ->where('pw.product_id IN (?)', $productId);

            $rows = $this->getConnRead()->fetchAll($query);
            foreach ($rows as $row)
            {
                if (!isset($value[$row['product_id']]))
                    $value[$row['product_id']] = array();
                $value[$row['product_id']][] = $row['store_id'];
            }
            return $value;
        }

        $query = $conn->select()
            ->from(array('pw' => $this->getRes()->getTableName('catalog/product_website')),
                's.store_id')
            ->joinInner(array('s' => $this->getRes()->getTableName('core/store')),
                's.website_id = pw.website_id',
                array())
            ->where('pw.product_id = ?', $productId);

        $value = $this->getConnRead()->fetchCol($query);

        return $value;
    }

    /**
     * @param int $productId - parent product id
     * @return array
     */
    public function getChildsIds($productId)
    {
        $data = false;

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = $conn->select()
            ->from(array('cpe' => $this->getRes()->getTableName('catalog/product')),
                array('cpe.entity_id')
                )
            ->joinInner(array('cpsl' => $this->getRes()->getTableName('catalog/product_super_link')),
                'cpe.entity_id = cpsl.product_id',
                array())
            ->joinInner(array('cpe_parent' => $this->getRes()->getTableName('catalog/product')),
                'cpsl.parent_id = cpe_parent.entity_id',
                array())
            ->where('cpe_parent.entity_id = ?', $productId);

        $result = $this->getConnRead()->fetchAll($query);

        if ($result !== false)
        {
            foreach ($result as $row)
            {
                $data[] = $row['entity_id'];
            }
        }

        return $data;
    }

    /**
     * @param int $productId - parent product id
     * @return array
     */
    public function getConfigurableAttributeCodes($productId)
    {
        $data = false;

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = $conn->select()
            ->from(array('csa' => $this->getRes()->getTableName('catalog/product_super_attribute')),
                array('eav.attribute_code'))
            ->joinInner(array('eav' => $this->getRes()->getTableName('eav/attribute')),
                'eav.attribute_id = csa.attribute_code',
                array())
            ->where('csa.product_id = ?', $productId);

        $result = $this->getConnRead()->fetchAll($query);

        if ($result !== false)
        {
            foreach ($result as $row)
            {
                $data[] = $row['attribute_code'];
            }
        }

        return $data;
    }

    public function explodeMultiselectValue($value)
    {
        $arr = array();
        if (!empty($value))
        {
            $arr = explode(',', $value);
            foreach ($arr as $k => $v) $arr[$k] = trim($v);
        }
        return $arr;
    }

    /**
     * @return Mage_Core_Model_Resource
     */
    public function getRes()
    {
        if (is_null($this->_res))
        {
            $this->_res = Mage::getSingleton('core/resource');
        }
        return $this->_res;
    }

    /**
     * @return Varien_Db_Adapter_Pdo_Mysql
     */
    public function getConnRead()
    {
        if (is_null($this->_conn_read))
        {
            $this->_conn_read = $this->getRes()->getConnection('core_read');
        }
        return $this->_conn_read;
    }

    /**
     * @return Varien_Db_Adapter_Pdo_Mysql
     */
    public function getConnWrite()
    {
        if (is_null($this->_conn_write))
        {
            $this->_conn_write = $this->getRes()->getConnection('core_write');
        }
        return $this->_conn_write;
    }

    public function getMagentoEdition()
    {
        if (is_callable('Mage::getEdition'))
        {
            return Mage::getEdition();
        }
        else
        {
            $features = array('Enterprise_Enterprise', 'Enterprise_AdminGws',
                              'Enterprise_Checkout', 'Enterprise_Customer');
            $editions = array(
                'Enterprise' => array(true, true, true, true), // ALL features
                'Professional' => array(true, false),          // ONLY the first
                'Community' => array(false),                   // NO features
            );

            foreach ($editions as $editionName => $featuresMap)
            {
                $match = true;

                foreach($featuresMap as $i => $featureValue)
                    $match = $match && ($featureValue === (bool) Mage::getConfig()->getModuleConfig($features[$i]));

                if ($match)
                    return $editionName;
            }

            return "Unknown";
        }
    }
}
