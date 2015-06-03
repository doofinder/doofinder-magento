<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   controllers
 * @package    Doofinder_Feed
 * @version    1.5.3
 */

/**
 * Index controller for Doofinder Feed
 *
 * @version    1.5.3
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $this->_redirect('/');
    }
}
