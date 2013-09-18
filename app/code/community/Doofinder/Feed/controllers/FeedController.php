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
        $this->getResponse()
          ->clearHeaders()
          ->setHeader('Content-Type','application/json; charset=utf-8')
          ->sendHeaders();

        $tools = Mage::getModel('doofinder_feed/tools');

        $config = array(
            'platform' => array(
                'name' => 'Magento',
                'edition' => $tools->getMagentoEdition(),
                'version' => Mage::getVersion()
            ),
            'module' => array(
                'version' => $this->_getVersion(),
                'feeds' => $this->_getFeeds(),
                'features' => $this->_getFeatures(),
            ),
        );

        die(json_encode($config));
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

    protected function _getFeeds()
    {
        $feeds = array();
        $baseUrl = Mage::getUrl('doofinder/feed');

        foreach (array_keys(Mage::app()->getStores(false, true)) as $storeCode)
        {
            $feeds[] = $baseUrl . '?store=' . $storeCode;
        }

        return $feeds;
    }

    protected function _getFeatures()
    {
        return array(
            'default' => array('offset', 'limit'),
            'magento' => array('store', 'grouped'),
        );
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
        $storeCode = $this->getRequest()->getParam('store');

        if (is_null($storeCode))
            $storeCode = Mage::app()->getStore()->getCode();

        if (is_null($storeCode))
            $storeCode = Mage_Core_Model_Store::DEFAULT_CODE;

        return $storeCode;
    }

    protected function _getGrouped()
    {
        $value = $this->getRequest()->getParam('grouped');
        if (in_array(strtolower($value), array('false', 'off')))
            return false;

        return (bool)$value;
    }
}
