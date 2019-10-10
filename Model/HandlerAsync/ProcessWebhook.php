<?php

namespace Skwirrel\Pim\Model\HandlerAsync;

use Skwirrel\Pim\Api\MappingInterface;
use Skwirrel\Pim\Client\ApiClient;
use Skwirrel\Pim\Model\ProductImporter;

class ProcessWebhook
{

    protected $importer;
    protected $apiClient;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var \Skwirrel\Pim\Api\MappingInterface
     */
    private $mapping;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        ProductImporter $importer,
        ApiClient $apiClient,
        MappingInterface $mapping

    ) {
        $this->logger = $logger;
        $this->importer = $importer;
        $this->apiClient = $apiClient;
        $this->mapping = $mapping;
    }

    public function process($payload)
    {
        $payload = json_decode($payload, true);

        foreach ($payload['update'] as $updateId) {
            $this->handleUpdate($updateId);
        }

    }

    public function handleUpdate($productId)
    {
        try {
            $response = $this->apiClient->makeRequest('getProductsByID', [
                'product_id' => [$productId],
                'include_categories' => true,
                'include_attachments' => true,
                'include_custom_class' => true,
                'include_trade_items' => true,
                'include_trade_item_prices' => true,
                'include_trade_item_translations' => true,
                'include_etim' => true,
                'include_related_products' => false,
                'include_product_translations' => true,
                'include_languages' => $this->mapping->getLanguages()

            ]);

            $products = (array)$response->products;
            foreach ($products as $id => $product) {

                $this->importer->import($product);
            }
            $this->logger->info('Handled update of product: ' . $productId);

        } catch (\Exception $e) {

            $this->logger->error($e->getMessage() . ' - ' . $e->getFile() . ' - ' . $e->getLine());
        }
    }
}