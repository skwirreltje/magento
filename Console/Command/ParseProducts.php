<?php

namespace Skwirrel\Pim\Console\Command;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Skwirrel\Pim\Import\Mapping\Config\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParseProducts extends Command
{

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;
    /**
     * @var \Magento\Framework\App\State
     */
    private $state;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute
     */
    private $entityAttribute;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute
     */
    private $attributeFactory;

    protected $attributeInfo = [];
    /**
     * @var \Skwirrel\Pim\Client\ApiClient
     */
    private $apiClient;
    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    private $dataHelper;
    /**
     * @var Reader
     */

    protected $entityType = 'product';

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $directoryReadFactory;

    /**
     * ParseProducts constructor.
     * @param null $name
     * @param \Skwirrel\Pim\Client\ApiClient $apiClient
     * @param \Skwirrel\Pim\Helper\Data $dataHelper
     * @param \Skwirrel\Pim\Model\Converter\ProductItem $productItemConverter
     * @param \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Skwirrel\Pim\Client\ApiClient $apiClient,
        \Skwirrel\Pim\Helper\Data $dataHelper,
        \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory,

        \Magento\Framework\App\State $state
    ) {

        parent::__construct();
        $this->state = $state;
        $this->apiClient = $apiClient;
        $this->dataHelper = $dataHelper;
        $this->directoryReadFactory = $directoryReadFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $basePath = $this->getFileBasePath();

        $data = $this->readDirectory($basePath);

        $attrs = [];
        foreach ($data as $prod) {
            foreach ($prod->attributes as $code => $attr) {
                if (isset($productAttributes[$code])) {
                    if ($productAttributes[$code]['data_type'] == 'select') {
                        if (!isset($productAttributes[$code]['options'])) {
                            $productAttributes[$code]['options'] = [];
                        }

                        foreach ($attr->value as $lang => $value) {
                            $productAttributes[$code]['options'][$lang][] = $value;
                        }
                    }
                }
            }
        }

    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("skwirrel:parseproducts");
        $this->setDescription("download products");
        parent::configure();
    }

    private function getFileBasePath()
    {
        $path = $this->dataHelper->getDirectory('var');
        if (!file_exists($path . '/import')) {
            mkdir($path . '/import');
        }
        return $path . '/import';
    }

    private function readDirectory($basePath)
    {

        $items = [];
        $reader = $this->directoryReadFactory->create($basePath);
        foreach ($reader->read() as $file) {
            $filepath = $basePath . DIRECTORY_SEPARATOR . $file;
            if (strpos($file, $this->entityType) !== false && substr($file, -4) == 'json') {
                $items[] = json_decode(file_get_contents($filepath));
            }

        }
        return $items;
    }


}
