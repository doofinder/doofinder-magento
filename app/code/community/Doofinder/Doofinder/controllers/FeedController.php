<?php
class Doofinder_Doofinder_FeedController extends Mage_Core_Controller_Front_Action
{
  const TXT_SEPARATOR = '|';
  const CATEGORY_SEPARATOR = '/';
  const CATEGORY_TREE_SEPARATOR = '>';

  protected static $csvHeader = array('id', 'title', 'link', 'description', 'price', 'sale_price', 'image_link', 'categories', 'availability', 'brand', 'gtin', 'mpn', 'extra_title_1', 'extra_title_2');

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

  // (/default)?/doofinder/feed/
  // (/default)?/doofinder/feed/index
  public function indexAction()
  {
    $request = $this->getRequest();
    $app = Mage::app();
    $app->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);

    // Store initialization

    $store = $app->getSafeStore($request->getParam('store', null));

    if (!$store->getCode() || !$store->getIsActive())
    {
      // 404
      $this->setFlag('', 'no-dispatch', true);
      return $this->_forward('noRoute');
    }

    $app->setCurrentStore($store);
    $tax = Mage::helper('tax');
    $baseURL = $store->getBaseUrl();

    // Prepare response headers

    $this->getResponse()
      ->clearHeaders()
      ->setHeader('Content-Type','text/plain; charset=UTF-8');

    // Get products

    $collection = Mage::getModel('catalog/product')
      ->getCollection()
      ->addAttributeToSelect('*')
      ->addAttributeToFilter('visibility', array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE))
      ->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED))
      ->addStoreFilter($store->getStoreId());

    // Output

    $this->getResponse()->sendHeaders();

    echo implode(self::TXT_SEPARATOR, self::$csvHeader).PHP_EOL;
    flush(); ob_flush();

    foreach ($collection as $product)
    {
      // ID
      echo $product->getId().self::TXT_SEPARATOR;

      // TITLE
      $product_title = self::cleanString($product->getName());
      echo $product_title.self::TXT_SEPARATOR;

      // LINK
      echo $product->getUrlInStore().self::TXT_SEPARATOR;

      // DESCRIPTION
      echo self::cleanString($product->getDescription()).self::TXT_SEPARATOR;

      $actualPrice = $tax->getPrice($product, $product->getPrice(), true);
      $specialPrice = $tax->getPrice($product, $product->getFinalPrice(), true);

      // PRICE
      if ($actualPrice > 0.0)
        echo number_format(self::cleanString($actualPrice), 2, '.', '').self::TXT_SEPARATOR;
      else
        echo "".self::TXT_SEPARATOR;

      // SALE PRICE
      if ($actualPrice > 0.0 && $actualPrice > $specialPrice)
        echo number_format(self::cleanString($specialPrice), 2, '.', '').self::TXT_SEPARATOR;
      else
        echo "".self::TXT_SEPARATOR;

      // IMAGE LINK
      echo $product->getSmallImageUrl().self::TXT_SEPARATOR;

      // PRODUCT CATEGORIES

      $categories = "";
      $categoryIds = $product->getCategoryIds();
      $nbcategories = count($categoryIds);
      $i = 0;

      foreach ($categoryIds as $catId)
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

        $categories .= self::cleanString($tree);
        if (++$i < $nbcategories)
          $categories .= self::CATEGORY_SEPARATOR;
      }

      echo $categories.self::TXT_SEPARATOR;

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

    die();
  }

  protected static function cleanString($text)
  {
    $text = strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    $text = str_replace(array(chr(9), chr(10)), " ", $text);
    return trim(preg_replace('/[\t\s]+|[|\r\n]/', " ", $text));
  }

  /**
   * Cleans a string in an extreme way to deal with conflictive strings like
   * titles that contains references that can be searched with or without
   * certain characters.
   *
   * TODO: Make it configurable from the admin.
   */
  protected static function cleanReferences($text)
  {
    $forbidden = array('-');
    return str_replace($forbidden, "", $text);
  }

  protected static function splitReferences($text)
  {
    return preg_replace("/([^\d\s])([\d])/", "$1 $2", $text);
  }
}