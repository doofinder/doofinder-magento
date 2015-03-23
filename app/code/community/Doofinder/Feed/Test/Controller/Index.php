<?php
class Doofinder_Feed_Test_Controller_Index extends EcomDev_PHPUnit_Test_Case_Controller
{
    /**
    * Index controller test
    *
    * @test
    * @loadFixture
    * @doNotIndexAll
    * @dataProvider dataProvider
    */
    public function testIndex()
    {
        $this->dispatch('/doofinder');
        $this->assertRequestRouteName('cms');
    }

    /**
    * Feed controller test
    *
    * @test
    * @loadFixture
    * @doNotIndexAll
    * @dataProvider dataProvider
    */
    public function testFeed()
    {
        $this->dispatch('/doofinder/feed');
        $this->assertRequestRouteName('doofinder/feed');
    }
}
