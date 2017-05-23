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
 * Associated Product Map Model for Doofinder Feed
 *
 * @version    1.8.7
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Model_Map_Product_Associated
    extends Doofinder_Feed_Model_Map_Product_Abstract
{
    protected function mapField($column)
    {
        $value = parent::mapField($column);

        if ($value == "")
            $value = $this->getParentMap()->mapField($column);

        return $value;
    }

    public function mapFieldDescription($params = array())
    {
        $value = $this->getCellValue(array('map' => $params['map']));

        if ($value == "")
            $value = $this->getParentMap()->mapField('description');

        return $value;
    }

    public function mapFieldLink($params = array())
    {
        $product = $this->getProduct();

        if ($product->isVisibleInSiteVisibility())
        {
            $value = $this->getCellValue(array('map' => $params['map']));
        }
        else
        {
            $value = $this->getParentMap()->mapField('link');

            if ($this->getConfigVar('associated_products_link_add_unique', 'columns'))
                $value = $this->addUrlUniqueParams(
                    $value,
                    $product,
                    $this->getParentMap()->getConfigurableAttributeCodes()
                );
        }

        return $value;
    }

    protected function addUrlUniqueParams($value, $product, $codes)
    {
        $params = array();

        foreach ($codes as $attrCode)
        {
            $data = $product->getData($attrCode);

            if (empty($data))
            {
                $this->skip = true;
                return $value;
            }

            $params[$attrCode] = $data;
        }

        $uri = Zend_Uri::factory($value);
        $scheme = $uri->getScheme();
        $query = $uri->getQueryAsArray();
        $port = $uri->getPort();

        if ($uri->valid())
        {
            $params = array_merge($query, $params);
            $uri->setQuery($params);

            if ($uri->valid())
                return $uri->getUri();

            $this->skip = true;
        }

        return $value;
    }

    public function mapFieldImageLink($params = array())
    {
        $value = $this->getCellValue(array('map' => $params['map']));

        if ($value == '')
            $value = $this->getParentMap()->mapField('image_link');

        return $value;
    }

    public function mapDirectiveAvailability($params = array())
    {
        $args = array('map' => $params['map']);
        $value = $this->getParentMap()->mapDirectiveAvailability($args);

        // gets out of stock if parent is out of stock
        if ($this->getConfig()->getOutOfStockStatus() == $value) {
            return $value;
        }

        return parent::mapDirectiveAvailability($params);
    }

    public function mapFieldBrand($params = array())
    {
        $args = array('map' => $params['map']);
        $value = "";

        // get value from parent first
        $value = $this->getParentMap()->mapField('brand');
        if ($value != "")
            return $value;

        $value = $this->getCellValue($args);

        return $value;
    }

    public function mapFieldProductType($params = array())
    {
        $args = array('map' => $params['map']);
        $value = "";

        // get value from parent first
        $value = $this->getParentMap()->mapField('product_type');
        if ($value != "")
            return htmlspecialchars_decode($value);

        $map_by_category = $this->getConfig()->getMapCategorySorted('product_type_by_category', $this->getStoreId());
        $category_ids = $this->getProduct()->getCategoryIds();
        if (empty($category_ids))
            $category_ids = $this->getParentMap()->getProduct()->getCategoryIds();
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
            return htmlspecialchars_decode($value);

        $value = $this->getCellValue($args);

        return htmlspecialchars_decode($value);
    }
}
