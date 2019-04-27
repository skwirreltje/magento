<?php
namespace Skwirrel\Pim\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class ApiClient
{
    /**
     * @var
     */
    protected $guzzleClient;
    protected $requestId = 0;
    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    private $data;

    public function __construct(\Skwirrel\Pim\Helper\Data $data)
    {
        $this->data = $data;
    }

    /**
     * @return \GuzzleHttp\Client
     */
    function getClient()
    {
        if (!$this->guzzleClient) {
            $this->guzzleClient = new Client(['base_uri' => $this->data->getConfig('skwirrel/api_options/url')]);
        }
        return $this->guzzleClient;
    }

    function getRequestId()
    {
        return $this->requestId += 1;
    }

    function makeRequest($method, $params = null, $id = null)
    {
        if (!$id) {
            $id = $this->getRequestId();
        }

        $request = $this->generateRequest($method, $params, $id);
        try{
            $response = $this->getClient()->send($request);
            $jsonResponse = json_decode($response->getBody()->getContents());
            if($jsonResponse && isset($jsonResponse->result)){
                return $jsonResponse->result;
            }

        }
        catch(\Exception $e){

        }
    }

    /**
     * @param $method
     * @param $params
     * @param $id
     * @return \GuzzleHttp\Psr7\Request
     */

    private function generateRequest($method, $params, $id)
    {
        $jsonData = [
            'jsonrpc' => '2.0',
            'method' => $method
        ];

        if ($params) {
            $jsonData['params'] = $params;
        }
        if ($id) {
            $jsonData['id'] = $id;
        }

        return new Request('POST', '', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Web-Service-Token' => $this->data->getConfig('skwirrel/api_options/api_token')
        ], json_encode($jsonData));


    }

    public function getConfigUrl()
    {
        return $this->data->getConfig('skwirrel/api_options/api_token');
    }


}