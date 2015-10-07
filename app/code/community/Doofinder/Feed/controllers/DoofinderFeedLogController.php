<?php

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
