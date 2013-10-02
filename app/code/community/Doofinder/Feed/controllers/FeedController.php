<?php
class Doofinder_Feed_FeedController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        if (!ini_get('safe_mode'))
            set_time_limit(3600);

        $options = array(
            'limit' => $this->_getLimit(),
            'offset' => $this->_getOffset(),
            'store_code' => $this->_getStoreCode(),
            'grouped' => $this->_getGrouped(),
        );

        $generator = Mage::getSingleton('doofinder_feed/generator', $options);
        $generator->run();
    }

    public function configAction()
    {
        $this->_sendJSONHeaders();

        $tools = Mage::getModel('doofinder_feed/tools');

        $storeCodes = array_keys(Mage::app()->getStores(false, true));
        $storesConfiguration = array();

        foreach ($storeCodes as $code)
        {
            $oStore = Mage::app()->getStore($code);
            $L = Mage::getStoreConfig('general/locale/code', $oStore->getId());
            $storesConfiguration[$code] = array(
                'language' => strtoupper(substr($L, 0, 2)),
                'currency' => $oStore->getCurrentCurrencyCode(),
                'prices' => true, // TODO(@carlosescri): Make configurable.
                'taxes' => true   // TODO(@carlosescri): Make configurable.
            );
        }


        $config = array(
            'platform' => array(
                'name' => 'Magento',
                'edition' => $tools->getMagentoEdition(),
                'version' => Mage::getVersion()
            ),
            'module' => array(
                'version' => $this->_getVersion(),
                'feed' => Mage::getUrl('doofinder/feed'),
                'options' => array(
                    'language' => $storeCodes,
                    'grouped' => true, // TODO(@carlosescri): Make configurable.
                ),
                'configuration' => $storesConfiguration
            ),
        );

        die(json_encode($config));
    }

    protected function _sendJSONHeaders()
    {
        $this->getResponse()
          ->clearHeaders()
          ->setHeader('Content-Type','application/json')
          ->sendHeaders();
    }

    protected function _dumpMessage($s_level, $s_message, $a_extra=array())
    {
        $error = array('status' => $s_level, 'message' => $s_message);

        if (is_array($a_extra) && count($a_extra))
            $error = array_merge($error, $a_extra);

        $this->_sendJSONHeaders();
        die(json_encode($error));
    }

    protected function _getVersion()
    {
        return Mage::getConfig()
            ->getNode()
            ->modules
            ->Doofinder_Feed
            ->version
            ->asArray();
    }

    protected function _getLimit()
    {
        $limit = $this->getRequest()->getParam('limit');

        return is_numeric($limit) ? (int) $limit : null;
    }

    protected function _getOffset()
    {
        $offset = $this->getRequest()->getParam('offset');

        return is_numeric($offset) ? (int) $offset : 0;
    }

    protected function _getStoreCode()
    {
        $storeCode = $this->getRequest()->getParam('language');

        if (is_null($storeCode))
            $storeCode = $this->getRequest()->getParam('store'); // Backwards...

        if (is_null($storeCode))
            $storeCode = Mage::app()->getStore()->getCode();

        try
        {
            return Mage::app()->getStore($storeCode)->getCode();
        }
        catch(Mage_Core_Model_Store_Exception $e)
        {
            $this->_dumpMessage('error', 'Invalid <language> parameter.',
                                array('code' => 'INVALID_OPTIONS'));
        }
    }

    protected function _getGrouped()
    {
        $value = $this->getRequest()->getParam('grouped');

        if (in_array(strtolower($value), array('false', 'off', 'no')))
            return false;

        return (bool)$value;
    }
}
