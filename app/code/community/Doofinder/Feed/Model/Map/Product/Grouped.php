<?php
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
        $price = $this->calcGroupPrice($this->getProduct());

        if ($price <= 0)
            $this->skip = true;

        return $price;
    }

    public function calcGroupPrice($product)
    {
        $price = 0.0;
        $ap = $product->getTypeInstance()->getAssociatedProducts();

        foreach ($ap as $associatedProduct)
            $price += $associatedProduct->getPrice();

        return $price; // Total price
    }
}
