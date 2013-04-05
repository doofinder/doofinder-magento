<?php
class Doofinder_Doofinder_FeedController extends Mage_Core_Controller_Front_Action
{
  const TXT_SEPARATOR = '|';
  const CATEGORY_SEPARATOR = '/';
  const CATEGORY_TREE_SEPARATOR = '>';

  protected static $csvHeader = array('id', 'title', 'link', 'description', 'price', 'sale_price', 'image_link', 'categories', 'availability', 'brand', 'gtin', 'mpn');

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
      ->setStoreId($store->getStoreId())
      ->getCollection()
      ->addAttributeToSelect('*')
      ->addStoreFilter($store->getStoreId());

    Mage::getSingleton('catalog/product_status')
      ->addVisibleFilterToCollection($collection);

    Mage::getSingleton('catalog/product_visibility')
      ->addVisibleInCatalogFilterToCollection($collection);

    // Output

    $this->getResponse()->sendHeaders();

    echo implode(self::TXT_SEPARATOR, self::$csvHeader).PHP_EOL;
    flush(); ob_flush();

    foreach ($collection as $product)
    {
      echo $product->getId().self::TXT_SEPARATOR;
      echo self::cleanString($product->getName()).self::TXT_SEPARATOR;
      echo $product->getUrlInStore().self::TXT_SEPARATOR;
      echo self::cleanString($product->getDescription()).self::TXT_SEPARATOR;

      $actualPrice = $tax->getPrice($product, $product->getPrice(), true);
      $specialPrice = $tax->getPrice($product, $product->getFinalPrice(), true);

      echo number_format(self::cleanString($actualPrice), 2, '.', '').self::TXT_SEPARATOR;

      if ($actualPrice > $specialPrice)
      {
        echo number_format(self::cleanString($specialPrice), 2, '.', '').self::TXT_SEPARATOR;
      }
      else
      {
        echo "".self::TXT_SEPARATOR;
      }

      echo $product->getSmallImageUrl().self::TXT_SEPARATOR;

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

      echo ($product->isInStock() ? 'in stock' : 'out of stock').self::TXT_SEPARATOR;
      echo self::cleanString($product->getAttributeText('manufacturer')).self::TXT_SEPARATOR;
      echo self::cleanString($product->getSku()).self::TXT_SEPARATOR;
      echo self::cleanString($product->getModel());

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
}