<?php
class Doofinder_Feed_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * $product         => Product instance
     * $oStore          => Store instance
     * $currencyConvert => Boolean, Convert prices to $oStore currency.
     * $useMinimalPrice => Boolean, See below.
     * $groupConfigurables => Boolean
     *
     * If $useMinimalPrice == true then, the price is checked against tier
     * prices. If there is a smaller price in the tier then that price is used
     * instead the regular one.
     *
     * So, if there is a special price defined and it is greater than the
     * minimal price found in tier, then it is not returned as the "sale_price".
     *
     * ----
     *
     * If a Fixed Product Tax exists for the product, then it is applied if
     * the $oStore settings are configured to do so.
     *
     * NOTICE: FPT are ALWAYS applied to prices including taxes. Configuration
     * is only applied to prices excluding taxes.
     */
    public function collectProductPrices(Mage_Catalog_Model_Product $product, $oStore, $currencyConvert=false, $useMinimalPrice=false, $groupConfigurables=true)
    {
        $prices = array();

        $weeeHelper = Mage::helper('weee');
        $taxHelper = Mage::helper('tax');
        $coreHelper = Mage::helper('core');

        // Tier Prices

        $tierPrices = $this->getProductTierPrices($product, $oStore);

        $minTierPrice = null;

        foreach ($tierPrices as $tier)
        {
            if ( is_null($minTierPrice) || $tier['base_price_excl_tax'] < $minTierPrice['base_price_excl_tax'] )
            {
                $minTierPrice = $tier;
                continue;
            }
        }

        // Prices

        if ( $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED )
        {
            $sub_prices = array();
            $sub_sale_prices = array();

            foreach($product->getTypeInstance()->getChildrenIds($product->getId()) as $ids)
            {
                foreach($ids as $id)
                {
                    $sub_product = Mage::getModel('catalog/product')->load($id);
                    $sub_product_price = $this->collectProductPrices($sub_product, $oStore, $currencyConvert, true, $groupConfigurables);

                    if (! empty($sub_product_price['price']['excluding_tax']))
                    {
                        $sub_prices[] = $sub_product_price['price']['excluding_tax'];

                        if (! empty($sub_product_price['sale_price']['excluding_tax']))
                        {
                            $sub_sale_prices[] = $sub_product_price['sale_price']['excluding_tax'];
                        }
                    }
                }
            }
            asort($sub_prices);
            asort($sub_sale_prices);

            $minPriceValue = array_shift($sub_prices);
            $minSalePriceValue = array_shift($sub_sale_prices);

            if ( $minPriceValue )
            {
                $prices['price_type'] = 'minimal';

                $prices['price']['excluding_tax'] = $taxHelper->getPrice($product, $minPriceValue, false, null, null, null, $oStore, null);
                $prices['price']['including_tax'] = $taxHelper->getPrice($product, $minPriceValue, true, null, null, null, $oStore, null);
                $prices['sale_price']['excluding_tax'] = $taxHelper->getPrice($product, $minSalePriceValue, false, null, null, null, $oStore, null);
                $prices['sale_price']['including_tax'] = $taxHelper->getPrice($product, $minSalePriceValue, true, null, null, null, $oStore, null);
            }
        }
        elseif ( $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE )
        {
            if ( method_exists($product->getPriceModel(), 'getTotalPrices') )
            {
                $bundle_price_excl_tax = $product->getPriceModel()->getTotalPrices($product, 'min', false, true);
                $bundle_price_incl_tax = $product->getPriceModel()->getTotalPrices($product, 'min', true, true);
            }
            else // Magento 1.5.0.1 + 1.5.1.0
            {
                $bundle_price_excl_tax = $product->getPriceModel()->getPricesDependingOnTax($product, 'min', false);
                $bundle_price_incl_tax = $product->getPriceModel()->getPricesDependingOnTax($product, 'min', true);
            }

            if ( $bundle_price_excl_tax )
            {
                $prices['price_type'] = 'minimal';

                $prices['price']['excluding_tax'] = $bundle_price_excl_tax;
                $prices['price']['including_tax'] = $bundle_price_incl_tax;
            }
        }
        else /* ! $product->isGrouped */
        {
            $prices['price_type'] = 'normal';

            $weeeTaxAmount = $weeeHelper->getAmountForDisplay($product);

            $weeeTaxAttributes = null;

            if ( $weeeHelper->typeOfDisplay($product, array(1, 2, 4), null, $oStore) )
            {
                $weeeTaxAmount = $weeeHelper->getAmount($product, null, null, $oStore->getWebsiteId(), false);
                $weeeTaxAttributes = $weeeHelper->getProductWeeeAttributesForDisplay($product);
            }

            // Precios originales y finales (segun Magento) sin Weee

            $base_price_excl_tax = $taxHelper->getPrice($product, $product->getPrice(), false, null, null, null, $oStore, null);
            $base_price_incl_tax = $taxHelper->getPrice($product, $product->getPrice(), true, null, null, null, $oStore, null);

            $final_price_excl_tax = $taxHelper->getPrice($product, $product->getFinalPrice(), false, null, null, null, $oStore, null);
            $final_price_incl_tax = $taxHelper->getPrice($product, $product->getFinalPrice(), true, null, null, null, $oStore, null);

            if ( $minTierPrice && $useMinimalPrice
                && $minTierPrice['base_price_excl_tax'] < $final_price_excl_tax)
            {
                $prices['price_type'] = 'minimal';

                $base_price_excl_tax = $minTierPrice['base_price_excl_tax'];
                $base_price_incl_tax = $minTierPrice['base_price_incl_tax'];
            }

            // Algunas preguntas

            $inclFptOnly = $weeeHelper->typeOfDisplay($product, 0, null, $oStore);                     // Including FPT only
            $inclFptAndDescription = $weeeHelper->typeOfDisplay($product, 1, null, $oStore);           // Including FPT and FPT description
            $exclFptAndDescriptionFinalPrice = $weeeHelper->typeOfDisplay($product, 2, null, $oStore); // Excluding FPT, FPT description, final price
            $exclFpt = $weeeHelper->typeOfDisplay($product, 3, null, $oStore);                         // Excluding FPT
            $inclFptAndDescriptionWithTaxes = $weeeHelper->typeOfDisplay($product, 4, null, $oStore);  // Including FPT and FPT description [incl. FPT VAT]

            // Elegimos y calculamos los precios finales

            if ( $final_price_excl_tax >= $base_price_excl_tax )
            {
                $prices['price']['excluding_tax'] = $base_price_excl_tax;
                $prices['price']['including_tax'] = $base_price_incl_tax;

                if ( $weeeTaxAmount )
                {
                    $prices['price']['including_tax'] += $weeeTaxAmount;

                    if ( $inclFptOnly || $inclFptAndDescription || $inclFptAndDescriptionWithTaxes )
                        $prices['price']['excluding_tax'] += $weeeTaxAmount;
                }
            }
            else
            {
                $prices['price']['excluding_tax'] = $base_price_excl_tax;
                $prices['price']['including_tax'] = $base_price_incl_tax;

                $prices['sale_price']['excluding_tax'] = $final_price_excl_tax;
                $prices['sale_price']['including_tax'] = $final_price_incl_tax;

                $originalWeeeTaxAmount = $weeeHelper->getOriginalAmount($product);

                if ( $weeeTaxAmount )
                {
                    $prices['price']['including_tax'] += $originalWeeeTaxAmount;
                    $prices['sale_price']['including_tax'] += $weeeTaxAmount;

                    if ( $inclFptOnly || $inclFptAndDescription || $inclFptAndDescriptionWithTaxes )
                    {
                        $prices['price']['excluding_tax'] += $originalWeeeTaxAmount;
                        $prices['sale_price']['excluding_tax'] += $weeeTaxAmount;
                    }
                }
            }

            if ( $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE && $groupConfigurables && $useMinimalPrice )
            {
                $childProducts = $product->getTypeInstance()->getUsedProducts();

                foreach ( $childProducts as $child )
                {
                    $childPrices = $this->collectProductPrices($child, $oStore, false, $useMinimalPrice, $groupConfigurables);

                    // Compare regular price
                    if ( $childPrices['price']['excluding_tax'] < $prices['price']['excluding_tax'] )
                    {
                        $prices['price']['excluding_tax'] = $childPrices['price']['excluding_tax'];
                        $prices['price']['including_tax'] = $childPrices['price']['including_tax'];
                        $prices['price']['overriden'] = true;
                    }

                    // Compare sale price
                    if ( array_key_exists('sale_price', $childPrices) )
                    {
                        if ( ! array_key_exists('sale_price', $prices)
                            || $childPrices['sale_price']['excluding_tax'] < $prices['sale_price']['excluding_tax'] )
                        {
                            $prices['sale_price']['excluding_tax'] = $childPrices['sale_price']['excluding_tax'];
                            $prices['sale_price']['including_tax'] = $childPrices['sale_price']['including_tax'];
                            $prices['sale_price']['overriden'] = true;
                        }
                    }
                }
            }
        }

        if ( isset($prices['sale_price']['excluding_tax']) &&
            $prices['price']['excluding_tax'] <= $prices['sale_price']['excluding_tax'] )
        {
            unset($prices['sale_price']['excluding_tax']);
            unset($prices['sale_price']['including_tax']);
        }

        if ( $prices['price']['excluding_tax'] <= 0 )
        {
            unset($prices['price']['excluding_tax']);
            unset($prices['price']['including_tax']);
        }

        foreach ( array('price', 'sale_price') as $priceType )
        {
            if ( !isset($prices[$priceType]) )
                continue;
            foreach ( $prices[$priceType] as $priceMode => $priceValue )
            {
                if ( $currencyConvert )
                    $priceValue = $oStore->convertPrice($priceValue, false, false);

                $prices[$priceType][$priceMode] = $priceValue;
            }
        }

        return $prices;
    }

    public function getProductTierPrices(Mage_Catalog_Model_Product $product, $oStore)
    {
        if (is_null($product))
            return array();

        $prices = array();
        $taxHelper = Mage::helper('tax');

        // Get Tier Prices

        $tierPrices = $product->getTierPrice(null);

        if (! is_array($tierPrices))
            $tierPrices = (array) $tierPrices;

        foreach ( $tierPrices as $price )
        {
            $result = array();

            if ( $price['website_id'] != $oStore->getWebsiteId() && $price['website_id'] != 0 )
                continue;

            $result['price_qty'] = $price['price_qty'] * 1;  // make int

            if ( $price['price'] < $product->getFinalPrice() )
                $result['save_percent'] = ceil(100 - ((100 / $product->getFinalPrice()) * $price['price']));

            $result['base_price_excl_tax'] = $taxHelper->getPrice($product, $price['website_price'], false, null, null, null, $oStore, null);
            $result['base_price_incl_tax'] = $taxHelper->getPrice($product, $price['website_price'], true, null, null, null, $oStore, null);

            $prices[] = $result;
        }

        return $prices;
    }
}
