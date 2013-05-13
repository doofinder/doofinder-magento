<?php
class Doofinder_Doofinder_FeedController extends Mage_Core_Controller_Front_Action
{
  const TXT_SEPARATOR = '|';
  const CATEGORY_SEPARATOR = '/';
  const CATEGORY_TREE_SEPARATOR = '>';

  private static $csvHeader = array('id', 'title', 'link', 'description', 'price', 'sale_price', 'image_link', 'categories', 'availability', 'brand', 'gtin', 'mpn', 'extra_title_1', 'extra_title_2');

  private $_aCategories;
  private $_iCurrentStoreId;
  private $_oCurrentStore;
  private $_oTaxHelper;

  // (/default)?/doofinder/feed/version
  public function versionAction()
  {
    $request = $this->getRequest();
    $app = Mage::app();
    $app->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);

    $this->getResponse()
      ->clearHeaders()
      ->setHeader('Content-Type','text/plain; charset=UTF-8')
      ->sendHeaders();

    die(Mage::getConfig()->getNode()->modules->Doofinder_Doofinder->version);
  }

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

  private function _countProducts()
  {
    $collection = $this->_getProductCollection();
    return $collection->getSize();
  }

  public function _addProductToFeed($args)
  {
    $row = $args['row'];

    // Load the current product
    $product = Mage::getModel('catalog/product');
    $product->setData($row);
    $product->setStoreId($this->_iCurrentStoreId);
    $product->getResource()->load($product, $row['entity_id']);

    // ID
    echo $product->getId().self::TXT_SEPARATOR;

    // TITLE
    $product_title = self::cleanString($product->getName());
    echo $product_title.self::TXT_SEPARATOR;

    // LINK
    echo $product->getUrlInStore().self::TXT_SEPARATOR;

    // DESCRIPTION
    echo self::cleanString($product->getDescription()).self::TXT_SEPARATOR;

    $actualPrice = $this->_oTaxHelper->getPrice($product, $product->getPrice(), true);
    $specialPrice = $this->_oTaxHelper->getPrice($product, $product->getFinalPrice(), true);

    // PRICE
    echo number_format(self::cleanString($actualPrice), 2, '.', '').self::TXT_SEPARATOR;

    // SALE PRICE
    if ($actualPrice > $specialPrice)
    {
      echo number_format(self::cleanString($specialPrice), 2, '.', '').self::TXT_SEPARATOR;
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

  private function _initStore()
  {
    $app = Mage::app();
    $app->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);

    $store = $app->getSafeStore($this->getRequest()->getParam('store', null));

    if (!$store || !$store->getCode() || !$store->getIsActive())
      return false;

    $app->setCurrentStore($store);

    $this->_oCurrentStore = $store;
    $this->_iCurrentStoreId = $store->getStoreId();

    return true;
  }

  // (/default)?/doofinder/feed/
  // (/default)?/doofinder/feed/index
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

    echo implode(self::TXT_SEPARATOR, self::$csvHeader).PHP_EOL;
    flush(); ob_flush();

    $iTotal = $this->_countProducts();
    $iChunk = 1000;

    for ($iOffset = 0; $iOffset < $iTotal; $iOffset += $iChunk)
    {
      $iLimit = min($iTotal - $iOffset, $iChunk);
      $collection = $this->_getProductCollection($iOffset, $iLimit);

      Mage::getSingleton('core/resource_iterator')->walk(
        $collection->getSelect(),
        array(array($this, '_addProductToFeed'))
      );
    }

    die();
  }

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
   * TODO: Make it configurable from the admin.
   */
  private static function cleanReferences($text, $repl = "")
  {
    $forbidden = array('-');
    return str_replace($forbidden, $repl, $text);
  }

  private static function splitReferences($text)
  {
    $s = preg_replace("/([^\d\s])([\d])/", "$1 $2", $text);
    $s = preg_replace("/([\d])([a-zA-Z-])/", "$1 $2", $s);

    return self::cleanReferences($s);
  }
}