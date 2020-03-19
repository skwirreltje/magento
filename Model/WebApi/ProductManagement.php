<?php
namespace Skwirrel\Pim\Model\WebApi;

use Skwirrel\Pim\Client\ApiClient;
use Skwirrel\Pim\Model\Mapping;
use Skwirrel\Pim\Model\ProductImporter;
use Skwirrel\Pim\WebApi\ProductManagementInterface;
use Skwirrel\Pim\WebApi\WebhookParamsInterface;
use Magento\Framework\MessageQueue\PublisherInterface;

class ProductManagement implements ProductManagementInterface
{

    /**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    private $request;
    /**
     * @var \Skwirrel\Pim\Model\ProductImporter
     */
    private $importer;
    /**
     * @var \Skwirrel\Pim\Client\ApiClient
     */
    private $apiClient;
    /**
     * @var \Magento\Framework\MessageQueue\PublisherInterface
     */
    private $publisher;
    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    private $data;

    public function __construct(
        \Magento\Framework\Webapi\Rest\Request $request,
        PublisherInterface $publisher,
        ProductImporter $importer,
        ApiClient $apiClient,
        \Skwirrel\Pim\Helper\Data $data

    ) {
        $this->request = $request;
        $this->importer = $importer;
        $this->apiClient = $apiClient;
        $this->publisher = $publisher;
        $this->data = $data;
    }

    public function getProduct($id = null)
    {
        return 'hello api GET return the $param ' . $id;
    }

    /**
     * @param string $jsonrpc
     * @param string $method
     * @param \Skwirrel\Pim\WebApi\WebhookParamsInterface $params
     * @return string
     */
    public function postChanges($jsonrpc, $method, $params)
    {

        $key = $this->data->getConfig('skwirrel/webhook_options/header_key');
        if($key && $key !== ''){
            if($key !== $this->request->getHeader('x-webhookauth')){
                print_r('NOT AUTHORIZED');
                return;
            }
        }

        $productParams = $params->getProduct();
        $changed = isset($productParams['change']) ? $productParams['change'] : [];
        foreach ($changed as $changedId) {
            $this->handleChange($changedId);
        }

        $created = isset($productParams['create']) ? $productParams['create'] : [];
        foreach ($created as $createdId) {
            $this->handleChange($createdId);
        }

        $deleted = isset($productParams['delete']) ? $productParams['delete'] : [];
        foreach ($deleted as $deleteId) {
            $this->handleDelete($deleteId);
        }
    }

    protected function handleChange($changedId)
    {

        $this->publisher->publish('process.webhook', json_encode(['update' => [$changedId]]));

    }

    private function handleDelete($deleteId)
    {
        $this->importer->deleteProductByExternalId($deleteId);
    }
}