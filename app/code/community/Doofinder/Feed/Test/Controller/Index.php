<?php
class Doofinder_Feed_Test_Controller_Index extends EcomDev_PHPUnit_Test_Case_Controller
{
    /**
    * Index controller test
    *
    * @test
    * @doNotIndexAll
    */
    public function testIndex()
    {
        $this->dispatch('doofinder');
        $this->assertRequestRoute('doofinder_feed/index/index');
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
        $this->dispatch('doofinder/feed');
        $this->assertRequestRoute('doofinder_feed/feed/index');
        // var_dump($this->getResponse());
        // $this->reset;
        // $this->assertRequestRoute('cms');
    }

    /**
    * Feed controller test
    *
    * @test
    * @loadFixture
    * @doNotIndexAll
    * @dataProvider dataProvider
    */
    public function testConfig()
    {
        $this->dispatch('doofinder/feed/config');
        $this->assertRequestRoute('doofinder_feed/feed/config');
        // $this->assertRequestRoute('cms');
    }
}
