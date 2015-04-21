<?php
class Doofinder_Feed_Test_Model_Product extends EcomDev_PHPUnit_Test_Case
{
    /**
    * Product price calculation test
    *
    * @test
    * @loadFixture
    * @doNotIndexAll
    * @dataProvider dataProvider
    */
    public function testGenerator($productId, $storeId)
    {
        $storeId = Mage::app()->getStore($storeId)->getId();

        // var_dump(Mage::getConfig()->getNode('default/tax/calculation'));

        $generator = Mage::helper('doofinder_feed');

        /* @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product')
            ->setStoreId($storeId)
            ->load($productId);

        $prices = $generator->collectProductPrices(
                $product,
                $storeId,
                false,
                false,
                true
            );

        $finalPriceInclTax = Mage::helper('tax')->getPrice($product, $product->getFinalPrice(), true);
        $finalPriceExclTax = Mage::helper('tax')->getPrice($product, $product->getFinalPrice(), false);

        // Check that prices w/ and without tax are different
        // $this->assertNotEquals(
        //     $finalPriceInclTax,
        //     $finalPriceExclTax
        // );

        $expected = $this->expected('%s-%s', $productId, $storeId);

        // Check that final price matches expected
        if (isset($prices['sale_price']))
        {
            // With tax
            $this->assertEquals(
                Mage::helper('core')->currency($finalPriceInclTax, true, false),
                Mage::helper('core')->currency($prices['sale_price']['including_tax'], true, false)
            );
            // Without tax
            $this->assertEquals(
                Mage::helper('core')->currency($finalPriceExclTax, true, false),
                Mage::helper('core')->currency($prices['sale_price']['excluding_tax'], true, false)
            );
        }
    }
}
