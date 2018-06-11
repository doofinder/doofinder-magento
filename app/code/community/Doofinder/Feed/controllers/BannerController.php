<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   controllers
 * @package    Doofinder_Feed
 * @version    1.8.24
 */

/**
 * Banner Controller
 *
 * @version    1.8.24
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_BannerController extends Mage_Core_Controller_Front_Action
{
    /**
     * Default action. Return to homepage.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->_redirect('/');
    }

    /**
     * Make call to API about registered banner click
     *
     * @return void
     */
    public function registerClickPostAction()
    {
        $isAjax = $this->getRequest()->isXmlHttpRequest();
        $bannerId = $this->getRequest()->getParam('bannerId', null);
        if ($isAjax && $bannerId) {
            $helper = Mage::helper('doofinder_feed/banner');
            $helper->registerBannerClick($bannerId);
        }
    }
}
