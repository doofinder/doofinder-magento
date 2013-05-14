<?php
class Doofinder_Doofinder_FeedController extends Mage_Core_Controller_Front_Action
{
  const TXT_SEPARATOR = '|';
  const CATEGORY_SEPARATOR = '/';
  const CATEGORY_TREE_SEPARATOR = '>';

  private static $csvHeader = array('id', 'title', 'link', 'description',
                                    'price', 'sale_price', 'image_link',
                                    'categories', 'availability', 'brand',
                                    'gtin', 'mpn', 'extra_title_1',
                                    'extra_title_2');
  private $_aCategories;
  private $_iCurrentStoreId;
  private $_oCurrentStore;
  private $_oTaxHelper;

  /**
   * Outputs the version number of the module in plain text.
   *
   * URL: (/default)?/doofinder/feed/version
   */
  public function versionAction()
  {
    $request = $this->getRequest();
    $app = Mage::app();
    $app->loadAreaPart(
      Mage_Core_Model_App_Area::AREA_FRONTEND,
      Mage_Core_Model_App_Area::PART_EVENTS);

    $this->getResponse()
      ->clearHeaders()
      ->setHeader('Content-Type','text/plain; charset=UTF-8')
      ->sendHeaders();

    die(Mage::getConfig()->getNode()->modules->Doofinder_Doofinder->version);
  }

  /**
   * Outputs a JSON string describing the Magento installation and the Doofinder
   * module configuration.
   *
   * URL: (/default)?/doofinder/feed/config
   */
  public function configAction()
  {
    $request = $this->getRequest();
    $app = Mage::app();
    $app->loadAreaPart(
      Mage_Core_Model_App_Area::AREA_FRONTEND,
      Mage_Core_Model_App_Area::PART_EVENTS);

    $this->getResponse()
      ->clearHeaders()
      ->setHeader('Content-Type','application/json; charset=UTF-8')
      ->sendHeaders();

    $oConfig = Mage::getConfig()->getNode();
    $feeds = array();

    foreach ($app->getStores() as $store)
    {
      $code = $store->getCode();
      if (!$this->_initStore($code))
        continue;

      $feeds[] = array(
        'id' => $this->_iCurrentStoreId,
        'code' => $code,
        'url' => $store->getUrl('doofinder/feed', array('_store_to_url' => true)),
        'length' => $this->_countProducts()
      );
    }

    $cfg = array(
      "platform" => array(
        "name" => "Magento",
        "version" => Mage::getVersion()
      ),
      "module" => array(
        "version" => $oConfig->modules->Doofinder_Doofinder->version->asArray(),
        "feeds" => $feeds
      )
    );

    echo json_encode($cfg);
    exit();
  }

  /**
   * Returns a collection of (int) $limit products from (int) $offset.
   *
   * @param int Offset (Default: 0)
   * @param int Limit (Default: 0 - Unlimited)
   * @return Collection products
   */
  private function _getProductCollection($offset = 0, $limit = 0)
  {
    $collection = Mage::getModel('catalog/product')
      ->setStoreId($this->_iCurrentStoreId)
      ->getCollection();

    // Products should be enabled
    $collection->addAttributeToFilter('status', 1);

    // Products should have visibility for the search
    $visibility = array(
      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH
    );

    $collection->addAttributeToFilter('visibility', $visibility);
    $collection->addAttributeToSelect('*');

    // Limit the number of products in this collection
    if ($limit != 0)
      $collection->getSelect()->limit($limit, $offset);

    return $collection;
  }

  /**
   * Returns the deepest categories paths for a product.
   * @param object Product
   * @return array of strings.
   */
  private function _getCategoriesFor($oProduct)
  {
    if ($this->_aCategories === null)
      $this->_aCategories = array();

    $categories = array();

    $categoryIds = $oProduct->getAvailableInCategories();
    $nbcategories = count($categoryIds);

    $i = 0;

    foreach ($categoryIds as $catId)
    {
      if (!isset($this->_aCategories[$catId]))
      {
        $category = Mage::getModel('catalog/category')->load($catId);
        $tree = "";
        $parents = $category->getParentCategories();
        $nbparents = count($parents);
        $j = 0;

        foreach ($parents as $parentCategory)
        {
          $tree .= $parentCategory->getName();
          if (++$j < $nbparents)
            $tree .= self::CATEGORY_TREE_SEPARATOR;
        }

        $this->_aCategories[$catId] = self::cleanString($tree);
      }

      $categories[] = $this->_aCategories[$catId];
    }

    sort($categories);
    $nbcategories = count($categories);
    $result = array();

    for ($i = 1; $i < $nbcategories; $i++)
    {
      if (strpos($categories[$i], $categories[$i - 1]) === 0)
        continue;
      $result[] = $categories[$i - 1];
    }
    $result[] = $categories[$i - 1];

    return $result;
  }

  /**
   * Returns the number of products of the data feed.
   * @return integer
   */
  private function _countProducts()
  {
    $collection = $this->_getProductCollection();
    return $collection->getSize();
  }

  /**
   * Outputs a product of the feed based on current configuration.
   */
  public function _addProductToFeed($args)
  {
    $row = $args['row'];

    // Load the current product
    $product = Mage::getModel('catalog/product');
    $product->setData($row);
    $product->setStoreId($this->_iCurrentStoreId);
    $product->getResource()->load($product, $row['entity_id']);

    $actualPrice = $this->_oTaxHelper->getPrice($product,
                                                $product->getPrice(), true);
    $salePrice = $this->_oTaxHelper->getPrice($product,
                                              $product->getFinalPrice(), true);

    if ($actualPrice <= 0 && $salePrice <= 0)
      return;

    // ID
    echo $product->getId().self::TXT_SEPARATOR;

    // TITLE
    $product_title = self::cleanString($product->getName());
    echo $product_title.self::TXT_SEPARATOR;

    // LINK
    echo $product->getUrlInStore().self::TXT_SEPARATOR;

    // DESCRIPTION
    echo self::cleanString($product->getDescription()).self::TXT_SEPARATOR;

    // PRICE
    if ($actualPrice <= 0)
      $actualPrice = $salePrice;

    echo number_format(self::cleanString($actualPrice), 2, '.', '').self::TXT_SEPARATOR;

    // SALE PRICE
    if ($actualPrice > $salePrice)
    {
      echo number_format(self::cleanString($salePrice), 2, '.', '').self::TXT_SEPARATOR;
    }
    else
    {
      echo "".self::TXT_SEPARATOR;
    }

    // IMAGE LINK
    echo $product->getSmallImageUrl().self::TXT_SEPARATOR;

    // PRODUCT CATEGORIES
    echo implode(self::CATEGORY_SEPARATOR, $this->_getCategoriesFor($product)).self::TXT_SEPARATOR;

    // AVAILABILITY
    echo ($product->isAvailable() ? 'in stock' : 'out of stock').self::TXT_SEPARATOR;

    // BRAND
    echo self::cleanString($product->getAttributeText('manufacturer')).self::TXT_SEPARATOR;

    // GTIN
    echo self::cleanString($product->getSku()).self::TXT_SEPARATOR;

    // MPN
    echo self::cleanString($product->getModel()).self::TXT_SEPARATOR;

    // EXTRA TITLE 1: TN-2220 => TN2220
    echo self::cleanReferences($product_title).self::TXT_SEPARATOR;

    // EXTRA TITLE 2: TN2220 => TN 2220
    echo self::splitReferences($product_title);

    echo PHP_EOL;
    flush(); ob_flush();
  }

  /**
   * Get the store code from $_GET and init the store in the object instance.
   */
  private function _initStore($storeCode = null)
  {
    $app = Mage::app();
    $app->loadAreaPart(
      Mage_Core_Model_App_Area::AREA_FRONTEND,
      Mage_Core_Model_App_Area::PART_EVENTS);

    if (! ($store = $this->getRequest()->getParam('___store', null)))
      $store = $this->getRequest()->getParam('store', $storeCode);

    $store = $app->getSafeStore($store);

    if (!$store || !$store->getCode() || !$store->getIsActive())
      return false;

    $app->setCurrentStore($store);

    $this->_oCurrentStore = $store;
    $this->_iCurrentStoreId = $store->getStoreId();

    return true;
  }

  /**
   * Index Action
   *
   * Controller to output the data feed in text/plain format.
   *
   * @param integer $_GET limit Optional. If present the action will only output
   *                            a limited number of results starting from the
   *                            value of the "offset" parameter (defaults to 0).
   * @param integer $_GET offset Optional. Used only with "limit".
   * @param integer $_GET chunk_size Size of the results block got from database
   *                                on each query.
   * @param string $_GET store Optional. Defaults to the default store. Code of
   *                           the store we want to get the data from.
   * @param string $_GET ___store Alias of "store".
   *
   * Sample URLs:
   *
   * (/<store>)?/doofinder/feed/index
   * (/<store>)?/doofinder/feed/
   * (/<store>)?/doofinder/feed?chunk_size=1000
   * (/<store>)?/doofinder/feed?offset=0&limit=10
   * (/<store>)?/doofinder/feed?limit=10
   * /doofinder/feed?___store=english
   * /doofinder/feed?store=spanish
   *
   */
  public function indexAction()
  {
    $request = $this->getRequest();

    // Store initialization
    if ($this->_initStore() === false)
    {
      // 404
      $this->setFlag('', 'no-dispatch', true);
      return $this->_forward('noRoute');
    }

    $this->_oTaxHelper = Mage::helper('tax');

    // Prepare response headers

    $this->getResponse()
      ->clearHeaders()
      ->setHeader('Content-Type','text/plain; charset=UTF-8');

    // Send headers

    $this->getResponse()->sendHeaders();

    // Send products

    $iLimit = intval($this->getRequest()->getParam('limit', 0));
    $iChunk = intval($this->getRequest()->getParam('chunk_size', 1000));
    $iOffset0 = intval($this->getRequest()->getParam('offset', 0));

    // (/default)?/doofinder/feed?offset=0&limit=10
    // (/default)?/doofinder/feed?limit=10
    if ($iLimit)
    {
      if ($iOffset0 === 0)
      {
        echo implode(self::TXT_SEPARATOR, self::$csvHeader).PHP_EOL;
        flush(); ob_flush();
      }

      $collection = $this->_getProductCollection($iOffset0, $iLimit);
      Mage::getSingleton('core/resource_iterator')->walk(
        $collection->getSelect(),
        array(array($this, '_addProductToFeed'))
      );
    }
    // (/default)?/doofinder/feed?chunk_size=1000
    // (/default)?/doofinder/feed
    else
    {
      $iTotal = $this->_countProducts();

      echo implode(self::TXT_SEPARATOR, self::$csvHeader).PHP_EOL;
      flush(); ob_flush();

      for ($iOffset = 0; $iOffset < $iTotal; $iOffset += $iChunk)
      {
        $iLimit = min($iTotal - $iOffset, $iChunk);
        $collection = $this->_getProductCollection($iOffset, $iLimit);

        Mage::getSingleton('core/resource_iterator')->walk(
          $collection->getSelect(),
          array(array($this, '_addProductToFeed'))
        );
      }
    }

    exit();
  }

  /**
   * Cleans the string passed as parameter to use it in the data feed.
   * @param string
   * @return string
   */
  private static function cleanString($text)
  {
    $text = strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    $text = str_replace(array(chr(9), chr(10)), " ", $text);
    $text = str_replace(array("|", "/"), "-", $text);

    return trim(preg_replace('/[\t\s]+|[\r\n]/', " ", $text));
  }

  /**
   * Cleans a string in an extreme way to deal with conflictive strings like
   * titles that contains references that can be searched with or without
   * certain characters.
   *
   * Ex: TN-2000 => TN2000
   *
   * TODO: Make it configurable from the admin.
   *
   * @param string
   * @param string Optional replacement string
   * @return string
   */
  private static function cleanReferences($text, $repl = "")
  {
    $forbidden = array('-');
    return str_replace($forbidden, $repl, $text);
  }

  /**
   * Separates alphabetical characters from numeric ones by adding a blank.
   *
   * Ex: TN2000 => TN 2000
   *
   * @param string
   * @return string
   */
  private static function splitReferences($text)
  {
    $s = preg_replace("/([^\d\s])([\d])/", "$1 $2", $text);
    $s = preg_replace("/([\d])([a-zA-Z-])/", "$1 $2", $s);

    return self::cleanReferences($s);
  }
}