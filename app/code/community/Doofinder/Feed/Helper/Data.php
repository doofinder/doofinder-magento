<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Helpers
 * @package    Doofinder_Feed
 * @version    1.5.8
 */

/**
 * Data helper for Doofinder Feed
 *
 * @version    1.5.8
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
     * Panel info messages.
     */
    const STATUS_DISABLED    = 'Disabled';
    const STATUS_PENDING    = Mage_Cron_Model_Schedule::STATUS_PENDING;
    const STATUS_RUNNING    = Mage_Cron_Model_Schedule::STATUS_RUNNING;
    const STATUS_SUCCESS    = Mage_Cron_Model_Schedule::STATUS_SUCCESS;
    const STATUS_MISSED     = Mage_Cron_Model_Schedule::STATUS_MISSED;
    const STATUS_WAITING     = 'Waiting...';
    const STATUS_ERROR      = Mage_Cron_Model_Schedule::STATUS_ERROR;
    const JOB_CODE          = 'doofinder_feed_generate';

    const MSG_EMPTY = "Currently there is no message.";
    const MSG_PENDING = "The new process of generating the feed has been registered and it's waiting to be activated.";
    const MSG_DISABLED = "The feed generator for this view is currently disabled.";
    const MSG_WAITING = "Waiting for registering the new process of generating the feed.";


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
        if (!isset($prices['price'])) return $prices;
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
        $weeeHelper = Mage::helper('weee');
        $taxHelper = Mage::helper('tax');
        $coreHelper = Mage::helper('core');

        $minimal_prices = array(
            'price' => array(
                'including_tax' => 0,
                'excluding_tax' => 0
            ),
            'sale_price' => array(
                'including_tax' => 0,
                'excluding_tax' => 0
            )
        );

        $childrenIds = $product->getTypeInstance()->getChildrenIds($product->getId());
        $childrenIds = $childrenIds[Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED];

        if (empty($childrenIds) || !is_array($childrenIds)) {
            return $minimal_prices;
        }

        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection
            ->addIdFilter($childrenIds)
            ->addAttributeToSelect('*')
            ->load();

        foreach($collection as $product)
        {
            $sub_prices = $this->collectProductPrices($product, $this->store, $this->currencyConvert, $this->useMinimalPrice, $this->groupConfigurables);

            if (! empty($sub_prices['price']['excluding_tax'])) {
                if ($minimal_prices['price']['excluding_tax'] === 0 ||
                    $minimal_prices['price']['excluding_tax'] > $sub_prices['price']['excluding_tax'])
                    $minimal_prices = $sub_prices;
            }
        }

        return $minimal_prices;
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
    public function getStoreConfig($storeCode = '') {
        $xmlName = Mage::getStoreConfig('doofinder_cron/schedule_settings/name', $storeCode);
        $config = array(
            'enabled'   =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/enabled', $storeCode),
            'display_price'     =>  Mage::getStoreConfig('doofinder_cron/feed_settings/display_price', $storeCode),
            'grouped'   =>  Mage::getStoreConfig('doofinder_cron/feed_settings/grouped', $storeCode),
            'stepSize'  =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/step', $storeCode),
            'stepDelay' =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/delay', $storeCode),
            'frequency' =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/frequency', $storeCode),
            'time'      =>  explode(',', Mage::getStoreConfig('doofinder_cron/schedule_settings/time', $storeCode)),
            'storeCode' =>  $storeCode,
            'xmlName'   =>  $this->_processXmlName($xmlName, $storeCode),
            'reset'     =>  Mage::getStoreConfig('doofinder_cron/schedule_settings/reset', $storeCode),
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

    /**
     * Creates new schedule entry.
     * @param Doofinder_Feed_Model_Cron $process
     */

    public function createNewSchedule(Doofinder_Feed_Model_Cron $process) {
        $helper = Mage::helper('doofinder_feed');

        $config = $helper->getStoreConfig($process->getStoreCode());

        // Set new schedule time
        $delayInMin = intval($config['stepDelay']);
        $timescheduled = strftime("%Y-%m-%d %H:%M:%S",  mktime(date("H"), date("i") + $delayInMin, date("s"), date("m"), date("d"), date("Y")));

        // Prepare new process data
        $status = $helper::STATUS_RUNNING;
        $nextRun = '-';

        // Set process data and save
        $process->setStatus($status)
            ->setNextRun('-')
            ->setNextIteration($timescheduled)
            ->save();

        Mage::helper('doofinder_feed/log')->log($process, Doofinder_Feed_Helper_Log::STATUS, $helper->__('Scheduling the next step for %s', $timescheduled));
    }

    public function getScheduledAt($time = null, $frequency = null, $timezoneOffset = true) {
        $parts = array($time[0], $time[1], $time[2], date('m'), date('d'));
        $offset = $this->getTimezoneOffset();

        $now = time();
        $start = mktime($parts[0] - $offset, $parts[1], $parts[2], $parts[3], $parts[4]);

        if ($start < $now) {
            switch ($frequency) {
                case self::CRON_MONTHLY:
                    $parts[3] += 1;
                    break;

                case self::CRON_WEEKLY:
                    $parts[4] += 7;
                    break;

                case self::CRON_DAILY:
                    $parts[4] += 1;
                    break;
            }
        }

        if ($timezoneOffset) {
            $parts[0] -= $offset;
        }

        return strftime("%Y-%m-%d %H:%M:%S", mktime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4]));
    }

    public function getTimezoneOffset() {
        $timezone = Mage::getStoreConfig('general/locale/timezone');
        $backTimezone = date_default_timezone_get();
        // Set relative timezone
        date_default_timezone_set($timezone);
        $offset = (date('Z') / 60 / 60);
        // Revoke server timezone
        date_default_timezone_set($backTimezone);
        return $offset;
    }

    /**
     * Get path to feed file.
     *
     * @return string
     */
    public function getFeedDirectory()
    {
        return Mage::getBaseDir('media').DS.'doofinder';
    }

    /**
     * Get path to feed file.
     *
     * @return string
     */
    public function getFeedPath($storeCode)
    {
        $config = $this->getStoreConfig($storeCode);

        return $this->getFeedDirectory().DS.$config['xmlName'];
    }

    /**
     * Get path to feed file.
     *
     * @return string
     */
    public function getFeedTemporaryPath($storeCode)
    {
        return $this->getFeedPath($storeCode) . '.tmp';
    }

    /**
     * Creates feed directory.
     *
     * @param string $dir
     * @return bool
     */
    public function createFeedDirectory()
    {
        $dir = $this->getFeedDirectory();

        if ((!file_exists($dir) && !mkdir($dir, 0777, true)) || !is_dir($dir)) {
           Mage::throwException('Could not create directory: '.$dir);
        }

        return true;
    }
}
