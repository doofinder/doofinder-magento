<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Helpers
 * @package    Doofinder_Feed
 * @version    1.8.23
 */

/**
 * Data helper for Doofinder Feed
 *
 * @version    1.8.23
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_Helper_Banner extends Mage_Core_Helper_Abstract
{
    const XML_BANNER_SETTINGS = 'doofinder_search/banner_settings';

    /**
     * @var Doofinder_Feed_Helper_Search
     */
    protected $searchHelper;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->searchHelper = Mage::helper('doofinder_feed/search');
    }

    /**
     * Get banner data
     *
     * @return array|null
     */
    public function getBanner()
    {
        $banner = null;
        if ($this->isBannerEnabled()) {
            $banner = $this->searchHelper->getDoofinderBannerData();
        }
        return $banner;
    }

    /**
     * Register Banner Display in Doofinder API.
     *
     * @param integer $bannerId
     * @return void
     */
    public function registerBannerDisplay($bannerId)
    {
        $this->searchHelper->getSearchClient()->registerBannerDisplay($bannerId);
    }

    /**
     * Register Banner Click in Doofinder API.
     *
     * @param $bannerId
     * @return void
     */
    public function registerBannerClick($bannerId)
    {
        $this->searchHelper->getSearchClient()->registerBannerClick($bannerId);
    }

    /**
     * Retrieve if banner feature is enabled.
     *
     * @return boolean
     */
    public function isBannerEnabled()
    {
        return Mage::getStoreConfig(self::XML_BANNER_SETTINGS . '/enabled', Mage::app()->getStore());
    }

    /**
     * Get banner placement from store config.
     *
     * @return string|null
     */
    public function getInsertionPoint()
    {
        return Mage::getStoreConfig(self::XML_BANNER_SETTINGS . '/insertion_point', Mage::app()->getStore());
    }

    /**
     * Get banner placement from store config.
     *
     * @return string|null
     */
    public function getInsertionMethod()
    {
        return Mage::getStoreConfig(self::XML_BANNER_SETTINGS . '/insertion_method', Mage::app()->getStore());
    }

}
