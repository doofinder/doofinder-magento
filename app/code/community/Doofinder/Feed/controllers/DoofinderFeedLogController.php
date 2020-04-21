<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   controllers
 * @package    Doofinder_Feed
 * @version    1.8.31
 */

class Doofinder_Feed_DoofinderFeedLogController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Override _isAllowed method
     */
    protected function _isAllowed()
    {
        return true;
    }

    /**
     * View log for specified process.
     */
    public function viewAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }
}
