<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.32
 */

/**
 * Grouped Product Map Model for Doofinder Feed
 *
 * @version    1.8.32
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Model_Map_Product_Grouped
    extends Doofinder_Feed_Model_Map_Product_Abstract
{
    /**
     * Grouped products doesn't have special price.
     *
     * @param string $field
     *
     * @return float
     */
    public function getProductPrice($field)
    {
        $price = $this->getMinPrice($this->getProduct(), $field);

        if ($price <= 0) {
            $this->skip = true;
        }

        return $price;
    }

    public function calcGroupPrice($product)
    {
        $price = 0.0;
        $associates = $product->getTypeInstance()->getAssociatedProducts();

        foreach ($associates as $associatedProduct) {
            $price += $associatedProduct->getPrice();
        }

        return $price; // Total price
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param string $field
     *
     * @return float|mixed|null
     */
    public function getMinPrice($product, $field = 'price')
    {
        $price = null;

        foreach ($product->getTypeInstance()->getAssociatedProducts() as $ap) {
            if ($price === null) {
                $price = $this->getPriceByField($ap, $field);
            } else {
                $price = min($price, $this->getPriceByField($ap, $field));
            }
        }

        return $price === null ? 0.0 : $price;
    }

    /**
     * Get product price according to field
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $field
     *
     * @return float|null
     */
    private function getPriceByField($product, $field)
    {
        switch ($field) {
            case 'price':
                return $product->getPrice();

            case 'sale_price':
            default:
                $salePrice = $product->getPriceModel()->getFinalPrice(null, $product);
                return $product->getPrice() <= $salePrice ? null : $salePrice;
        }
    }
}
