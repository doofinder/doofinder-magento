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
 * Bundle Product Map Model for Doofinder Feed
 *
 * @version    1.8.7
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Model_Map_Product_Bundle
    extends Doofinder_Feed_Model_Map_Product_Abstract
{
    public function getPrice()
    {
        $price = 0.0;

        if (!$this->hasSpecialPrice())
        {
            $price = $this->calcMinimalPrice($this->getProduct());
        }
        else
        {
            $price = $this->calcMinimalPrice($this->getProduct());
        }

        if ($price <= 0)
            $this->skip = true;

        return $price;
    }

    public function calcMinimalPrice($product) {
        $price = 0.0;

        if ($this->getConfig()->compareMagentoVersion(
                array('major' => 1, 'minor' => 6, 'revision' => 0, 'patch' => 0)))
            $_prices = $product->getPriceModel()->getPrices($product);
        else
            $_prices = $product->getPriceModel()->getTotalPrices($product);

        if (is_array($_prices))
            $price = min($_prices);
        else
            $price = $_prices;

        return $price;
    }

    public function getSpecialPrice()
    {
        $price = $this->calcMinimalPrice($this->getProduct());

        $special_price_percent = $this->getProduct()->getSpecialPrice();

        if ($special_price_percent <= 0 || $special_price_percent > 100)
            return 0;

        $special_price = (($special_price = (100 - $special_price_percent) * $price / 100) > 0 ? $special_price : 0);

        return $special_price;
    }

    protected function mapDirectiveSalePrice($params = array())
    {
        return null;
    }
}
