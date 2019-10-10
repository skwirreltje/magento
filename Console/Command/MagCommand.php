<?php

namespace Skwirrel\Pim\Console\Command;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ObjectManager;
use Magento\ImportExport\Model\Import;
use Skwirrel\Pim\Api\MappingInterface;
use Skwirrel\Pim\Client\ApiClient;
use Skwirrel\Pim\Eav\Attribute\Frontend\Attachments;
use Skwirrel\Pim\Import\Mapping\Config\Reader;
use Skwirrel\Pim\Model\ProductImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MagCommand extends Command
{

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @var \Skwirrel\Pim\Api\MappingInterface
     */
    private $mapping;
    /**
     * @var \Skwirrel\Pim\Model\ModelFactory
     */
    private $importModelFactory;
    /**
     * @var \Skwirrel\Pim\Console\Progress
     */
    protected $progress;
    /**
     * @var \Skwirrel\Pim\Model\ImportManager
     */
    private $importManager;


    protected $settings = [];
    /**
     * @var \Skwirrel\Pim\Console\Command\ArrayAdapterFactory
     */
    private $arrayAdapterFactory;
    /**
     * @var \Skwirrel\Pim\Model\ProductBuilderFactory
     */
    private $productBuilderFactory;
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;
    /**
     * @var \Magento\Catalog\Model\Product\Gallery\Processor
     */
    private $galleryProcessor;

    /**
     * @var \Magento\Eav\Setup\EavSetup
     */
    private $eavSetup;
    /**
     * @var \Magento\Setup\Module\DataSetup
     */
    private $dataSetup;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var \Skwirrel\Pim\Model\ProductImporter
     */
    private $importer;
    /**
     * @var \Skwirrel\Pim\Client\ApiClient
     */
    private $apiClient;


    /**
     * ParseProducts constructor.
     * @param \Skwirrel\Pim\Console\Progress $progress
     * @param \Skwirrel\Pim\Model\ImportManager $importManager
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Psr\Log\LoggerInterface $logger,
        ProductImporter $importer,
        ApiClient $apiClient,
        MappingInterface $mapping


    ) {

        parent::__construct();

        $this->state = $state;

        $this->logger = $logger;
        $this->importer = $importer;
        $this->apiClient = $apiClient;
        $this->mapping = $mapping;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);


        $this->handleUpdate(9558);


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



    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("skwirrel:mag");
        $this->setDescription("Run a full import");
        parent::configure();
    }


}


//
