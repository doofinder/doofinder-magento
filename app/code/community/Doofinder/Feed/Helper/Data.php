<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Helpers
 * @package    Doofinder_Feed
 * @version    1.5.3
 */

/**
 * Data helper for Doofinder Feed
 *
 * @version    1.5.3
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Helper_Data extends Mage_Core_Helper_Abstract
{
    private $store = null;

    private $currencyConvert = false;

    private $useMinimalPrice = false;

    private $groupConfigurables = true;

    private $minTierPrice = null;

    const CRON_DAILY     =    Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_DAILY;
    const CRON_WEEKLY    =    Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY;
    const CRON_MONTHLY   =    Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY;


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
        $this->store = $oStore;
        $this->currencyConvert = $currencyConvert;
        $this->useMinimalPrice = $useMinimalPrice;
        $this->groupConfigurables = $groupConfigurables;

        $weeeHelper = Mage::helper('weee');
        $taxHelper = Mage::helper('tax');
        $coreHelper = Mage::helper('core');

        // Tier Prices

        $tierPrices = $this->getProductTierPrices($product, $oStore);

        foreach ($tierPrices as $tier)
        {
            if ( is_null($this->minTierPrice) || $tier['base_price_excl_tax'] < $this->minTierPrice['base_price_excl_tax'] )
            {
                $this->minTierPrice = $tier;
                continue;
            }
        }

        if ( $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED )
        {
            $prices = $this->_getGroupedProductPrice($product);
        }
        elseif ( $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE )
        {
            $prices = $this->_getBundleProductPrice($product);
        }
        else /* ! $product->isGrouped */
        {
            $prices = $this->_getProductPrice($product);
        }

        $prices = $this->_cleanPrices($prices);

        foreach ( array('price', 'sale_price') as $priceType )
        {
            if ( !isset($prices[$priceType]) )
                continue;
            foreach ( $prices[$priceType] as $priceMode => $priceValue )
            {
                if ( $currencyConvert ) {
                    $priceValue = $oStore->convertPrice($priceValue, false, false);
                }
                $prices[$priceType][$priceMode] = $priceValue;
            }
        }

        return $prices;
    }

    protected function _cleanPrices($prices)
    {
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

        return $prices;
    }

    protected function _getProductPrice($product)
    {
        $prices = array();

        $weeeHelper = Mage::helper('weee');
        $taxHelper = Mage::helper('tax');
        $coreHelper = Mage::helper('core');

        $prices['price_type'] = 'normal';

        $weeeTaxAmount = $weeeHelper->getAmountForDisplay($product);

        $weeeTaxAttributes = null;

        if ( $weeeHelper->typeOfDisplay($product, array(1, 2, 4), null, $this->store) )
        {
            $weeeTaxAmount = $weeeHelper->getAmount($product, null, null, $this->store->getWebsiteId(), false);
            $weeeTaxAttributes = $weeeHelper->getProductWeeeAttributesForDisplay($product);
        }

        // Precios originales y finales (segun Magento) sin Weee

        $base_price_excl_tax = $taxHelper->getPrice($product, $product->getPrice(), false, null, null, null, $this->store, null);
        $base_price_incl_tax = $taxHelper->getPrice($product, $product->getPrice(), true, null, null, null, $this->store, null);

        $final_price_excl_tax = $taxHelper->getPrice($product, $product->getFinalPrice(), false, null, null, null, $this->store, null);
        $final_price_incl_tax = $taxHelper->getPrice($product, $product->getFinalPrice(), true, null, null, null, $this->store, null);

        if ( $this->minTierPrice && $this->useMinimalPrice
            && $this->minTierPrice['base_price_excl_tax'] < $final_price_excl_tax)
        {
            $prices['price_type'] = 'minimal';

            $base_price_excl_tax = $this->minTierPrice['base_price_excl_tax'];
            $base_price_incl_tax = $this->minTierPrice['base_price_incl_tax'];
        }

        // Algunas preguntas

        $inclFptOnly = $weeeHelper->typeOfDisplay($product, 0, null, $this->store);                     // Including FPT only
        $inclFptAndDescription = $weeeHelper->typeOfDisplay($product, 1, null, $this->store);           // Including FPT and FPT description
        $exclFptAndDescriptionFinalPrice = $weeeHelper->typeOfDisplay($product, 2, null, $this->store); // Excluding FPT, FPT description, final price
        $exclFpt = $weeeHelper->typeOfDisplay($product, 3, null, $this->store);                         // Excluding FPT
        $inclFptAndDescriptionWithTaxes = $weeeHelper->typeOfDisplay($product, 4, null, $this->store);  // Including FPT and FPT description [incl. FPT VAT]

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

        if ( $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE && $this->groupConfigurables && $this->useMinimalPrice )
        {
            $prices = $this->_getConfigurableProductPrice($product);
        }
        return $prices;
    }

    protected function _getConfigurableProductPrice($product, $prices)
    {
        $childProducts = $product->getTypeInstance()->getUsedProducts();

        foreach ( $childProducts as $child )
        {
            $childPrices = $this->collectProductPrices($child, $this->store, false, $this->useMinimalPrice, $this->groupConfigurables);

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
        return $prices;
    }

    protected function _getGroupedProductPrice($product)
    {
        $prices = array();

        $weeeHelper = Mage::helper('weee');
        $taxHelper = Mage::helper('tax');
        $coreHelper = Mage::helper('core');

        $sub_prices = array();
        $sub_sale_prices = array();

        $childrenIds = $product->getTypeInstance()->getChildrenIds($product->getId());
        $childrenIds = $childrenIds[Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED];

        if (empty($childrenIds) || !is_array($childrenIds)) {
            return array(
                'price' => array(
                    'including_tax' => 0,
                    'excluding_tax' => 0
                ),
                'sale_price' => array(
                    'including_tax' => 0,
                    'excluding_tax' => 0
                )
            );
        }

        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection
            ->addIdFilter($childrenIds)
            ->addAttributeToSelect('*')
            ->load();

        foreach($collection as $product)
        {
            $sub_product_price = $this->collectProductPrices($product, $this->store, $this->currencyConvert, true, $this->groupConfigurables);

            if (! empty($sub_product_price['price']['excluding_tax']))
            {
                $sub_prices[] = $sub_product_price['price']['excluding_tax'];

                if (! empty($sub_product_price['sale_price']['excluding_tax']))
                {
                    $sub_sale_prices[] = $sub_product_price['sale_price']['excluding_tax'];
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

            $prices['price']['excluding_tax'] = $taxHelper->getPrice($product, $minPriceValue, false, null, null, null, $this->store, null);
            $prices['price']['including_tax'] = $taxHelper->getPrice($product, $minPriceValue, true, null, null, null, $this->store, null);
            $prices['sale_price']['excluding_tax'] = $taxHelper->getPrice($product, $minSalePriceValue, false, null, null, null, $this->store, null);
            $prices['sale_price']['including_tax'] = $taxHelper->getPrice($product, $minSalePriceValue, true, null, null, null, $this->store, null);
        }

        return $prices;
    }

    protected function _getBundleProductPrice($product)
    {
        $prices = array();

        $weeeHelper = Mage::helper('weee');
        $taxHelper = Mage::helper('tax');
        $coreHelper = Mage::helper('core');

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

    /**
     * Gets store config for cron settings.
     * @param string $storeCode
     * @return array
     */
    public function getStoreConfig($storeCode = 'default') {
        $xmlName = Mage::getStoreConfig('doofinder_cron/settings/name', $storeCode);
        $config = array(
            'enabled'   =>  Mage::getStoreConfig('doofinder_cron/settings/enabled', $storeCode),
            'price'     =>  Mage::getStoreConfig('doofinder_cron/settings/minimal_price', $storeCode),
            'grouped'   =>  Mage::getStoreConfig('doofinder_cron/settings/grouped', $storeCode),
            'stepSize'  =>  Mage::getStoreConfig('doofinder_cron/settings/step', $storeCode),
            'stepDelay'     =>  Mage::getStoreConfig('doofinder_cron/settings/delay', $storeCode),
            'frequency' =>  Mage::getStoreConfig('doofinder_cron/settings/frequency', $storeCode),
            'time'      =>  explode(',', Mage::getStoreConfig('doofinder_cron/settings/time', $storeCode)),
            'storeCode' =>  $storeCode,
            'xmlName'   =>  $this->_processXmlName($xmlName, $storeCode),
        );
        return $config;
    }

    /**
     * Process xml filename
     * @param string $name
     * @return bool
     */
    private function _processXmlName($name = 'doofinder-{store_code}.xml', $code = 'default') {
        $pattern = '/\{\s*store_code\s*\}/';

        $newName = preg_replace($pattern, $code, $name);
        return $newName;
    }

    /**
     * Create cron expr string
     * @param string $time
     * @return mixed
     */
    private function _getCronExpr($time = null, $frequency = null) {

        if (!$time) return false;
        $time = explode(',', $time);

        $cronExprArray = array(
            intval($time[1]),
            intval($time[0]),
            ($frequency == self::CRON_MONTHLY) ? '1' : '*',
            '*',
            ($frequency == self::CRON_WEEKLY) ? '1' : '*',
        );
        $cronExprString = join(' ', $cronExprArray);

        return $cronExprString;
    }

    public function getScheduledAt($time = null, $frequency = null) {

        $week   = $frequency == self::CRON_WEEKLY ? 7 : 0;
        $month  = $frequency == self::CRON_MONTHLY ? 1 : 0;
        $day    = $frequency == self::CRON_DAILY ? 1 : $week;
        $timescheduled = strftime("%Y-%m-%d %H:%M:%S", mktime($time[0], $time[1], $time[2], date("m") + $month, date("d") + $day, date("Y")));
        return $timescheduled;
    }
}
