<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.11
 */

/**
 * Grouped Product Map Model for Doofinder Feed
 *
 * @version    1.8.11
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Model_Map_Product_Grouped
    extends Doofinder_Feed_Model_Map_Product_Abstract
{
    /**
     * Grouped products doesn't have special price.
     *
     * @return float
     */
    public function getPrice()
    {
        $price = $this->getMinPrice($this->getProduct());

        if ($price <= 0)
            $this->skip = true;

        return $price;
    }

    public function calcGroupPrice($product)
    {
        $price = 0.0;
        $associates = $product->getTypeInstance()->getAssociatedProducts();

        foreach ($associates as $associatedProduct)
            $price += $associatedProduct->getPrice();

        return $price; // Total price
    }

    public function getMinPrice($product)
    {
        $price = null;

        foreach ($product->getTypeInstance()->getAssociatedProducts() as $ap) {
            if ($price === null)
                $price = $ap->getPrice();
            else
                $price = min($price, $ap->getPrice());
        }

        return $price === null ? 0.0 : $price;
    }
}
