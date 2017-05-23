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
 * Feed controller for Doofinder Feed
 *
 * @version    1.8.7
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_FeedController extends Mage_Core_Controller_Front_Action
{

    /**
     * Send JSON headers
     */
    protected function _setJSONHeaders()
    {
        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Content-type', 'application/json; charset="utf-8"', true);
    }

    /**
     * Send XML headers
     */
    protected function _setXMLHeaders()
    {
        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Content-type', 'application/xml; charset="utf-8"', true);
    }

    /**
     * Check password
     *
     * @param string $storeCode
     * @return boolean
     */
    protected function _checkPassword($storeCode)
    {
        $password = Mage::getStoreConfig('doofinder_cron/feed_settings/password', $storeCode);
        return !$password || $this->getRequest()->getParam('password') == $password;
    }

    public function indexAction()
    {
        $storeCode = $this->_getStoreCode();

        // Do not proceed if password check fails
        if (!$this->_checkPassword($storeCode)) {
            return $this->_forward('defaultNoRoute');
        }

        $config = Mage::helper('doofinder_feed')->getStoreConfig($storeCode);

        // Set options for cron generator
        $options = array(
            '_limit_' => $this->_getInteger('limit', null),
            '_offset_' => 0,
            'store_code' => $config['storeCode'],
            'grouped' => (bool) $config['grouped'],
            'display_price' => (bool) $config['display_price'],
            'minimal_price' => $this->_getBoolean('minimal_price', false),
            'customer_group_id' => 0,
            'image_size' => $config['image_size'],
        );

        $generator = Mage::getSingleton('doofinder_feed/generator', $options);

        // Convert offset to product id
        $offset = $this->_getInteger('offset', 0);

        if ($offset > 0) {
            $collection = $generator->getProductCollection();
            $collection->getSelect()->limit(1, $offset);

            $item = $collection->fetchItem();

            $offset = $item ? $item->getEntityId() - 1 : -1;
        }

        $response = '';
        if ($offset >= 0) {
            $generator->setData('_offset_', $offset);
            $this->_setXMLHeaders();

            $response = $generator->run();

            ob_end_clean();
        }

        $this->getResponse()->setBody($response);
    }

    public function configAction()
    {
        $this->_setJSONHeaders();

        $helper = Mage::helper('doofinder_feed');

        $tools = Mage::getModel('doofinder_feed/tools');

        $storeCodes = array_keys(Mage::app()->getStores(false, true));
        $storesConfiguration = array();

        // Get file spath
        $filesUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'doofinder'.DS;
        $filesPath = Mage::getBaseDir('media').DS.'doofinder'.DS;

        foreach ($storeCodes as $code)
        {
            $settings = $helper->getStoreConfig($code, false);

            if ($settings['enabled'])
            {
                $filepath = $filesPath.$settings['xmlName'];
                $fileurl = $filesUrl.$settings['xmlName'];
                $feedUrl = $filesUrl.$settings['xmlName'];
                $feedExists = (bool) $this->_feedExists($filepath);
            }
            else
            {
                $feedUrl = Mage::getUrl('doofinder/feed', array('_store' => $code));
                $feedExists = true;
            }

            $oStore = Mage::app()->getStore($code);
            $L = Mage::getStoreConfig('general/locale/code', $oStore->getId());
            $password = Mage::getStoreConfig('doofinder_cron/feed_settings/password', $code);
            $storesConfiguration[$code] = array(
                'language' => strtoupper(substr($L, 0, 2)),
                'currency' => $oStore->getCurrentCurrencyCode(),
                'feed' =>  $feedUrl,
                'feed_exists' => $feedExists,
                'secured' => !empty($password),
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
                ),
                'configuration' => $storesConfiguration,
            ),
        );

        $response = Mage::helper('core')->jsonEncode($config);
        $this->getResponse()->setBody($response);
    }
    /**
     * Check if feed on filepath exists.
     * @param string $filepath
     * @return bool
     */
    protected function _feedExists($filepath = null) {
        if (file_exists($filepath)) {
            return true;
        }
        return false;
    }

    protected function _dumpMessage($s_level, $s_message, $a_extra=array())
    {
        $error = array('status' => $s_level, 'message' => $s_message);

        if (is_array($a_extra) && count($a_extra))
            $error = array_merge($error, $a_extra);

        $this->_setJSONHeaders();

        $response = Mage::helper('core')->jsonEncode($error);
        $this->getResponse()->setBody($response);
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

    protected function _getBoolean($param, $defaultValue = false)
    {
        $value = strtolower($this->getRequest()->getParam($param));

        if ( is_numeric($value) )
            return ((int)($value *= 1) > 0);

        $yes = array('true', 'on', 'yes');
        $no  = array('false', 'off', 'no');

        if ( in_array($value, $yes) )
            return true;

        if ( in_array($value, $no) )
            return false;

        return $defaultValue;
    }

    protected function _getInteger($param, $defaultValue)
    {
        $value = $this->getRequest()->getParam($param);
        if ( is_numeric($value) )
            return (int)($value *= 1);
        return $defaultValue;
    }

    /**
     * Creates directory.
     * @param string $dir
     * @return bool
     */
    protected function _createDirectory($dir = null) {
        if (!$dir) return false;

        if(!mkdir($dir, 0777, true)) {
           Mage::throwException('Could not create directory: '.$dir);
        }

        return true;
    }

    /*
        TEST TOOLS
    */

    // public function testsAction()
    // {
    //     if ( !in_array(Mage::helper('core/http')->getRemoteAddr(), array('127.0.0.1', '::1')) )
    //     {
    //         $this->norouteAction();
    //         return false;
    //     }

    //     $oStore           = Mage::app()->getStore($this->_getStoreCode());
    //     $bGrouped         = $this->_getBoolean('grouped', true);
    //     $bMinimalPrice    = $this->_getBoolean('minimal_price', false);
    //     $bCurrencyConvert = $this->_getBoolean('convert_currency', true);
    //     $iCustomerGroupId = $this->_getInteger('customer_group', 0);

    //     $ids = array(
    //         'simple' => array(166, 27),
    //         'grouped' => 54,
    //         'configurable' => 83,
    //         'virtual' => 142,
    //         'bundle' => 158,
    //         'downloadable' => 167
    //     );

    //     $data = array(
    //         'store' => array(
    //             'store_id' => $oStore->getStoreId(),
    //             'website_id' => $oStore->getWebsiteId(),
    //             'base_currency' => $oStore->getBaseCurrencyCode(),
    //             'current_currency' => $oStore->getCurrentCurrencyCode(),
    //             'default_currency' => $oStore->getDefaultCurrencyCode(),
    //         ),
    //         'products' => array(),
    //     );

    //     $rule = Mage::getModel('catalogrule/rule');
    //     $dataHelper = Mage::helper('doofinder_feed');

    //     foreach ($ids as $product_type => $ids)
    //     {
    //         foreach ((array) $ids as $id)
    //         {
    //             $product = Mage::getModel('catalog/product')
    //                 ->setStoreId($oStore->getStoreId())
    //                 ->setCustomerGroupId($iCustomerGroupId)
    //                 ->load($id);

    //             $data['products'][$id] = array(
    //                 'product_type' => $product_type,
    //                 'name' => $product->getName(),
    //             );

    //             $data['products'][$id] = array_merge(
    //                 $data['products'][$id],
    //                 $dataHelper->collectProductPrices($product, $oStore, $bCurrencyConvert, $bMinimalPrice, $bGrouped)
    //             );
    //         }
    //     }

    //     $this->_setJSONHeaders();

    //     $response = Mage::helper('core')->jsonEncode($data);
    //     $this->getResponse()->setBody($response);
    // }
}
