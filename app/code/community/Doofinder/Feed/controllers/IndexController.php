<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   controllers
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

/**
 * Index controller for Doofinder Feed
 *
 * @version    1.8.7
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $this->_redirect('/');
    }

    public function testAction() {
        $allStores = Mage::app()->getStores();
        foreach ($allStores as $store) {
            var_dump($store->getCode());
        }

    }
}
