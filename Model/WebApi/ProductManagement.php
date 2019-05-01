<?php
namespace Skwirrel\Pim\Model\WebApi;

use Skwirrel\Pim\Client\ApiClient;
use Skwirrel\Pim\Model\Mapping;
use Skwirrel\Pim\Model\ProductImporter;
use Skwirrel\Pim\WebApi\ProductManagementInterface;
use Skwirrel\Pim\WebApi\WebhookParamsInterface;

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

    public function __construct(
        \Magento\Framework\Webapi\Rest\Request $request,
        ProductImporter $importer,
        ApiClient $apiClient

    ) {
        $this->request = $request;
        $this->importer = $importer;
        $this->apiClient = $apiClient;
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
        try {
            $response = $this->apiClient->makeRequest('getProductsByID', [
                'product_id' => [$changedId],
                'include_categories' => true,
                'include_attachments' => true,
                'include_trade_items' => true,
                'include_trade_item_prices' => true,
                'include_trade_item_translations' => true,
                'include_etim' => true,
                'include_related_products' => false,
                'include_product_translations' => true,
                'include_languages' => ['en', 'nl']
            ]);

            $products = (array)$response->products;
            foreach ($products as $id => $product) {

                $this->importer->import($product);
            }

        } catch (\Exception $e) {

            print_r('error : '.$e->getMessage().' - '.$e->getFile().' - '.$e->getLine()) ;
            print_r($e->getTraceAsString()) ;
        }


    }

    private function handleDelete($deleteId)
    {
        $this->importer->deleteProductByExternalId($deleteId);
    }
}