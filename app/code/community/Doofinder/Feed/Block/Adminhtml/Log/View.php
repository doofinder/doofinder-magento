<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Block_Adminhtml_Log_View extends Mage_Adminhtml_Block_Widget_Grid
{
    protected $_defaultSort     = 'id';
    protected $_defaultDir      = 'desc';

    protected $_processId = null;

    public function __construct()
    {
        parent::__construct();

        $this->_processId = Mage::app()->getRequest()->getParam('processId', false);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('doofinder_feed/log_collection');

        if ($this->_processId) {
            $collection->getSelect()->where("process_id = $this->_processId");
        }

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('id', array(
            'header'    => Mage::helper('doofinder_feed')->__('ID'),
            'index'     => 'id',
            'type'  => 'number',
        ));

        if (!$this->_processId) {
            $this->addColumn('process_id', array(
                'header'    => Mage::helper('doofinder_feed')->__('Process ID'),
                'index'     => 'process_id',
                'type'  => 'number',
            ));
        }

        $this->addColumn('time', array(
            'header'    => Mage::helper('doofinder_feed')->__('Time'),
            'index'     => 'time',
            'type'  => 'datetime',
        ));

        $this->addColumn('type', array(
            'header'    => Mage::helper('doofinder_feed')->__('Type'),
            'index'     => 'type',
            'type'  => 'options',
            'options' => Mage::helper('doofinder_feed/log')->listLogTypes(),
        ));

        $this->addColumn('message', array(
            'header'    => Mage::helper('doofinder_feed')->__('Message'),
            'index'     => 'message',
            'type'  => 'text',
        ));

        return parent::_prepareColumns();
    }
}
