<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   controllers
 * @package    Doofinder_Feed
 * @version    1.8.17
 */

/**
 * Index controller for Doofinder Feed
 *
 * @version    1.8.17
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $this->_redirect('/');
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    public function testAction()
    {
        $allStores = Mage::app()->getStores();
        foreach ($allStores as $store) {
            // @codingStandardsIgnoreStart
            var_dump($store->getCode());
            // @codingStandardsIgnoreEnd
        }

    }
}
