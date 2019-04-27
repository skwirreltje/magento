<?php
namespace Skwirrel\Pim\WebApi;

interface ProductManagementInterface
{
    /**
     * @param int $id
     * @return mixed
     */
    public function getProduct($id = null);

    /**
     * @param string $jsonrpc
     * @param string $method
     * @param \Skwirrel\Pim\WebApi\WebhookParamsInterface $params
     * @return mixed
     */
    public function postChanges($jsonrpc, $method, $params);
}