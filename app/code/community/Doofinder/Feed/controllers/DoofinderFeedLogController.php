<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   controllers
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_DoofinderFeedLogController extends Mage_Adminhtml_Controller_Action
{
    /**
     * View log for specified process.
     */
    public function viewAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }
}
