<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.33

 * Class Doofinder_Feed_Model_Api_Client
 * The class responsible for connecting with Doofinder API
 */
class Doofinder_Feed_Model_Api_Client
{
    /**
     * @var string
     */
    protected $apiUrl;

    /**
     * @var Doofinder_Feed_Helper_ApiConfiguration
     */
    protected $helper;

    /**
     * Doofinder_Feed_Model_Api_Client constructor.
     */
    public function __construct()
    {
        $this->helper = Mage::helper('doofinder_feed/apiConfiguration');
    }

    /**
     * Send request to API
     * @param string $url
     * @param string $requestType
     * @param null|array $data
     * @return array
     */
    public function sendRequest($url, $requestType = 'GET', $data = null)
    {
        $contentType = null;
        if ($data) {
            $data = $this->encode($data);
            $contentType = 'application/json';
        }
        $client = $this->get($contentType);
        $client->write($requestType, $url, '1.1', null, $data);
        return $this->decode($client->read());
    }

    /**
     * Get prepared CURL client
     * @param string|null $contentType
     * @return Varien_Http_Adapter_Curl
     */
    public function get($contentType = null)
    {
        $client = new Varien_Http_Adapter_Curl();
        $apiKey = $this->helper->getApiKey();

        $headers = [
            sprintf('Authorization: Token %s', $apiKey),
            'accept: application/json'
        ];
        if ($contentType) {
            $headers[] = sprintf('Content-Type: %s', $contentType);
        }

        $client->addOptions([CURLOPT_HTTPHEADER => $headers]);
        $client->setConfig(['header' => false]);

        return $client;
    }

    /**
     * Build URL to API endpoint
     * @param string $uri
     * @return string
     */
    public function getUrl($uri)
    {
        if (!$this->apiUrl) {
            $this->apiUrl = $this->helper->getManagementServer() . '/api/v2';
        }
        return sprintf('%s/%s', $this->apiUrl, $uri);
    }

    /**
     * @param string $data
     * @return mixed
     */
    public function decode($data)
    {
        return json_decode($data, true);
    }

    /**
     * @param array $data
     * @return string
     */
    public function encode($data)
    {
        return json_encode($data);
    }
}
