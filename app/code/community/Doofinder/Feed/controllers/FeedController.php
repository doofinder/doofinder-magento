<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   controllers
 * @package    Doofinder_Feed
 * @version    1.8.18
 */

/**
 * Feed controller for Doofinder Feed
 *
 * @version    1.8.18
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
            // @codingStandardsIgnoreStart
            $collection->getSelect()->limit(1, $offset);
            // @codingStandardsIgnoreEnd

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

        foreach ($storeCodes as $code) {
            $settings = $helper->getStoreConfig($code, false);

            if ($settings['enabled']) {
                $filepath = $filesPath.$settings['xmlName'];
                $feedUrl = $filesUrl.$settings['xmlName'];
                $feedExists = (bool) $this->_feedExists($filepath);
            } else {
                $feedUrl = Mage::getUrl('doofinder/feed', array('_store' => $code));
                $feedExists = true;
            }

            $oStore = Mage::app()->getStore($code);
            $locale = Mage::getStoreConfig('general/locale/code', $oStore->getId());
            $password = Mage::getStoreConfig('doofinder_cron/feed_settings/password', $code);
            $storesConfiguration[$code] = array(
                'language' => strtoupper(substr($locale, 0, 2)),
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
    protected function _feedExists($filepath = null)
    {
        return Mage::helper('doofinder_feed')->fileExists($filepath);
    }

    protected function _dumpMessage($level, $message, $extra = array())
    {
        $error = array('status' => $level, 'message' => $message);

        if (is_array($extra) && !empty($extra))
            $error = array_merge($error, $extra);

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

        if ($storeCode === null)
            $storeCode = $this->getRequest()->getParam('store'); // Backwards...

        if ($storeCode === null)
            $storeCode = Mage::app()->getStore()->getCode();

        try
        {
            return Mage::app()->getStore($storeCode)->getCode();
        }
        catch(Mage_Core_Model_Store_Exception $e)
        {
            $this->_dumpMessage(
                'error',
                'Invalid <language> parameter.',
                array('code' => 'INVALID_OPTIONS')
            );
        }
    }

    protected function _getBoolean($param, $defaultValue = false)
    {
        $value = strtolower($this->getRequest()->getParam($param));

        if (is_numeric($value))
            return ((int)($value *= 1) > 0);

        $true = array('true', 'on', 'yes');
        $false  = array('false', 'off', 'no');

        if (in_array($value, $true))
            return true;

        if (in_array($value, $false))
            return false;

        return $defaultValue;
    }

    protected function _getInteger($param, $defaultValue)
    {
        $value = $this->getRequest()->getParam($param);
        if (is_numeric($value))
            return (int)($value *= 1);
        return $defaultValue;
    }

    /**
     * Creates directory.
     * @param string $dir
     * @return bool
     */
    protected function _createDirectory($dir = null)
    {
        return Mage::helper('doofinder_feed')->mkdir($dir);
    }
}
