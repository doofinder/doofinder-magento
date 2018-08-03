<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.26
 */

class Doofinder_Feed_Block_Banner extends Mage_Core_Block_Template
{
    /**
     * @var Doofinder_Feed_Helper_Banner
     */
    protected $helper;

    /**
     * Magento construct
     *
     * @return void
     */
    public function _construct()
    {
        $this->helper = Mage::helper('doofinder_feed/banner');
    }

    /**
     * Get Doofinder banner
     *
     * @return array|null
     */
    public function getBanner()
    {
        $banner = $this->helper->getBanner();
        if ($banner !== null) {
            $this->helper->registerBannerDisplay($banner['id']);
        }
        return $banner;
    }

    /**
     * Get AJAX url for register banner.
     *
     * @return mixed
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('doofinder/banner/registerClickPost');
    }

    /**
     * Get banner insertion point.
     *
     * @return null|string
     */
    public function getBannerInsertionPoint()
    {
        return $this->helper->getInsertionPoint();
    }

    /**
     * Get banner insertion method.
     *
     * @return null|string
     */
    public function getBannerInsertionMethod()
    {
        return $this->helper->getInsertionMethod();
    }
}
