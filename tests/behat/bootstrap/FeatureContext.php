<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;

use Behat\MinkExtension\Context\MinkContext;

use Behat\Mink\Exception\ExpectationException;

use SebastianBergmann\Diff\Differ;

/**
* Defines application features from the specific context.
*/
class FeatureContext extends MinkContext implements Context, SnippetAcceptingContext
{
     /**
     * @Then the response header :header should be :value
     */
    public function theResponseHeaderShouldBe($header, $value)
    {
        $header = $this->fixStepArgument($header);
        $value = $this->fixStepArgument($value);

        $this->assertSession()->responseHeaderEquals($header, $value);
    }

    /**
     * Extract items from feed
     *
     * @param string $feed
     * @return array
     */
    protected function extractItemsFromFeed($feed)
    {
        $xmlReader = new XMLReader();
        $xmlReader->xml($feed);
        $xmlReader->next('rss');
        $dom = $xmlReader->expand();

        $items = array();
        foreach ($dom->getElementsByTagName('item') as $node) {
            $item = array();

            foreach ($node->childNodes as $child) {
                $item[$child->nodeName] = $child->nodeValue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @Then the response should equal test feed
     */
    public function theResponseShouldEqualTestFeed()
    {
        $actualFeed = $this->getSession()->getPage()->getContent();
        $expectedFeed = file_get_contents($this->getMinkParameter('files_path') . '/feed.xml');

        $actual = $this->extractItemsFromFeed($actualFeed);
        $expected = $this->extractItemsFromFeed($expectedFeed);

        PHPUnit_Framework_Assert::assertEquals($expected, $actual);
    }
}
